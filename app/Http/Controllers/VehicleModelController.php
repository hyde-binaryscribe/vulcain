<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\MaintenanceType;
use App\Domain\Storage\LocationKind;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleModelLocation;
use App\Models\VehicleMotorization;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modèles de véhicule : on configure une fois un gabarit d'emplacements, et
 * les emplacements sont générés automatiquement à la création d'un véhicule
 * sur ce modèle (cf. VehicleController::store).
 */
class VehicleModelController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $usage = Vehicle::query()
            ->selectRaw('vehicle_model_id, count(*) as total')
            ->whereNotNull('vehicle_model_id')
            ->groupBy('vehicle_model_id')
            ->pluck('total', 'vehicle_model_id');

        $models = VehicleModel::query()
            ->with(['templateLocations', 'motorizations.maintenancePlans'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (VehicleModel $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'brand' => $m->brand,
                'model' => $m->model,
                'year' => $m->year,
                'coachbuilder' => $m->coachbuilder,
                'display_order' => $m->display_order,
                'is_active' => $m->is_active,
                'template' => $m->templateTree(),
                'emplacements_count' => $m->templateLocations->count(),
                'motorizations' => $m->motorizations->map(fn (VehicleMotorization $mo) => [
                    'name' => $mo->name,
                    'fuel' => $mo->fuel,
                    'plans' => $mo->maintenancePlans->map(fn ($p) => [
                        'type' => $p->type->value,
                        'label' => $p->label,
                        'interval_km' => $p->interval_km,
                        'interval_months' => $p->interval_months,
                    ])->values(),
                ])->values(),
                'vehicles_count' => (int) ($usage[$m->id] ?? 0),
            ]);

        return Inertia::render('VehicleModels/Index', [
            'models' => $models,
            'kinds' => LocationKind::options($this->tenant->organisation()->bagsEnabled()),
            'maintenanceTypes' => MaintenanceType::options(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $model = VehicleModel::create($data + ['is_active' => true]);

        $this->syncTemplate($model, $request->input('template', []));
        $this->syncMotorizations($model, $request->input('motorizations', []));

        return back()->with('status', 'Modèle de véhicule créé.');
    }

    public function update(Request $request, VehicleModel $vehicleModel): RedirectResponse
    {
        $data = $this->validated($request, $vehicleModel);

        $vehicleModel->update($data);

        $this->syncTemplate($vehicleModel, $request->input('template', []));
        $this->syncMotorizations($vehicleModel, $request->input('motorizations', []));

        return back()->with('status', 'Modèle de véhicule mis à jour.');
    }

    public function toggle(VehicleModel $vehicleModel): RedirectResponse
    {
        $vehicleModel->is_active = ! $vehicleModel->is_active;
        $vehicleModel->save();

        return back()->with('status', 'Statut du modèle mis à jour.');
    }

    public function destroy(VehicleModel $vehicleModel): RedirectResponse
    {
        // Les emplacements du gabarit sont supprimés en cascade ; les véhicules
        // déjà créés conservent leurs emplacements (le lien passe à null).
        $vehicleModel->delete();

        return back()->with('status', 'Modèle de véhicule supprimé.');
    }

    /**
     * @return array{name:string, brand:string, model:string, year:?int, coachbuilder:?string, display_order:int}
     */
    private function validated(Request $request, ?VehicleModel $current = null): array
    {
        $data = $request->validate([
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'coachbuilder' => ['nullable', 'string', 'max:100'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'template' => ['array'],
        ]);

        $name = VehicleModel::composeName(
            $data['brand'],
            $data['model'],
            $data['year'] ?? null,
            $data['coachbuilder'] ?? null,
        );

        // Unicité du libellé composé au sein de l'organisation.
        $exists = VehicleModel::query()
            ->where('name', $name)
            ->when($current !== null, fn ($q) => $q->whereKeyNot($current->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'brand' => 'Un modèle identique (marque / modèle / année / carrossier) existe déjà.',
            ]);
        }

        return [
            'name' => $name,
            'brand' => $data['brand'],
            'model' => $data['model'],
            'year' => $data['year'] ?? null,
            'coachbuilder' => $data['coachbuilder'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
        ];
    }

    /**
     * Reconstruit le gabarit d'emplacements du modèle à partir d'un arbre
     * fourni par le formulaire : liste de nœuds { name, kind, children[] }.
     *
     * @param  array<int, mixed>  $tree
     */
    private function syncTemplate(VehicleModel $model, array $tree): void
    {
        // Natures autorisées sur un véhicule (mobile / sac) ; le reste est ramené à « mobile ».
        $allowed = array_values(array_filter(
            LocationKind::cases(),
            fn (LocationKind $k) => $k->requiresVehicle(),
        ));
        $allowedValues = array_map(fn (LocationKind $k) => $k->value, $allowed);

        DB::transaction(function () use ($model, $tree, $allowedValues) {
            $model->templateLocations()->delete();

            $order = 0;
            $insert = function (array $nodes, ?int $parentId, int $depth) use (&$insert, &$order, $model, $allowedValues): void {
                if ($depth > 4) {
                    return; // garde-fou de profondeur
                }

                foreach ($nodes as $node) {
                    if (! is_array($node)) {
                        continue;
                    }
                    $name = trim((string) ($node['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $kind = (string) ($node['kind'] ?? 'mobile');
                    if (! in_array($kind, $allowedValues, true)) {
                        $kind = 'mobile';
                    }

                    $row = $model->templateLocations()->create([
                        'parent_id' => $parentId,
                        'name' => mb_substr($name, 0, 100),
                        'kind' => $kind,
                        'display_order' => $order++,
                    ]);

                    $children = $node['children'] ?? [];
                    if (is_array($children) && $children !== []) {
                        $insert($children, $row->id, $depth + 1);
                    }
                }
            };

            $insert($tree, null, 0);
        });
    }

    /**
     * Reconstruit les motorisations du modèle et leurs plans d'entretien à
     * partir du formulaire : liste de { name, fuel, plans: [{ type, label,
     * interval_km, interval_months }] }.
     *
     * @param  array<int, mixed>  $motorizations
     */
    private function syncMotorizations(VehicleModel $model, array $motorizations): void
    {
        $validTypes = array_map(fn (MaintenanceType $t) => $t->value, MaintenanceType::cases());

        DB::transaction(function () use ($model, $motorizations, $validTypes) {
            // La suppression des motorisations fait tomber leurs plans (cascade FK).
            $model->motorizations()->delete();

            $mOrder = 0;
            foreach ($motorizations as $mo) {
                if (! is_array($mo)) {
                    continue;
                }
                $name = trim((string) ($mo['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $fuel = trim((string) ($mo['fuel'] ?? ''));
                $motor = $model->motorizations()->create([
                    'name' => mb_substr($name, 0, 100),
                    'fuel' => $fuel !== '' ? mb_substr($fuel, 0, 30) : null,
                    'display_order' => $mOrder++,
                ]);

                $pOrder = 0;
                foreach (($mo['plans'] ?? []) as $plan) {
                    if (! is_array($plan)) {
                        continue;
                    }
                    $type = (string) ($plan['type'] ?? '');
                    if (! in_array($type, $validTypes, true)) {
                        continue;
                    }

                    $km = $this->intOrNull($plan['interval_km'] ?? null);
                    $months = $this->intOrNull($plan['interval_months'] ?? null);
                    // Un plan sans périodicité ne générerait aucune échéance : on l'ignore.
                    if ($km === null && $months === null) {
                        continue;
                    }

                    $label = trim((string) ($plan['label'] ?? ''));
                    $motor->maintenancePlans()->create([
                        'type' => $type,
                        'label' => $label !== '' ? mb_substr($label, 0, 100) : null,
                        'interval_km' => $km,
                        'interval_months' => $months,
                        'display_order' => $pOrder++,
                    ]);
                }
            }
        });
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
