<?php

namespace App\Http\Controllers;

use App\Domain\Storage\LocationKind;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleModelLocation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            ->with('templateLocations')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (VehicleModel $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'display_order' => $m->display_order,
                'is_active' => $m->is_active,
                'template' => $m->templateTree(),
                'emplacements_count' => $m->templateLocations->count(),
                'vehicles_count' => (int) ($usage[$m->id] ?? 0),
            ]);

        return Inertia::render('VehicleModels/Index', [
            'models' => $models,
            'kinds' => LocationKind::options($this->tenant->organisation()->bagsEnabled()),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $model = VehicleModel::create([
            'name' => $data['name'],
            'display_order' => $data['display_order'],
            'is_active' => true,
        ]);

        $this->syncTemplate($model, $request->input('template', []));

        return back()->with('status', 'Modèle de véhicule créé.');
    }

    public function update(Request $request, VehicleModel $vehicleModel): RedirectResponse
    {
        $data = $this->validated($request, $vehicleModel);

        $vehicleModel->update([
            'name' => $data['name'],
            'display_order' => $data['display_order'],
        ]);

        $this->syncTemplate($vehicleModel, $request->input('template', []));

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
     * @return array{name:string, display_order:int}
     */
    private function validated(Request $request, ?VehicleModel $current = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('vehicle_models', 'name')
                    ->where('organisation_id', $this->tenant->id())
                    ->whereNull('deleted_at')
                    ->ignore($current?->id),
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'template' => ['array'],
        ]);

        return [
            'name' => $data['name'],
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
}
