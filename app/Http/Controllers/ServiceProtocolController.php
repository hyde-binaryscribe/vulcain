<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\ProtocolFieldType;
use App\Domain\Fleet\ProtocolPhase;
use App\Domain\Support\Severity;
use App\Models\ServiceProtocol;
use App\Models\ServiceProtocolField;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Constructeur des protocoles de service (ouverture / fermeture), configurables
 * par portée (défaut, type, modèle, véhicule) : champs typés et déclencheurs
 * d'alerte. La résolution à l'usage se fait en cascade (voir ServiceProtocol).
 */
class ServiceProtocolController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        VehicleType::ensureSeeded($this->tenant->organisation());

        $protocols = ServiceProtocol::query()->with('fields')->get()
            ->map(fn (ServiceProtocol $p) => [
                'scope' => $p->scope(),
                'vehicle_type' => $p->vehicle_type,
                'vehicle_model_id' => $p->vehicle_model_id,
                'vehicle_id' => $p->vehicle_id,
                'phase' => $p->phase->value,
                'fields' => $p->fields->map(fn (ServiceProtocolField $f) => [
                    'label' => $f->label,
                    'type' => $f->type->value,
                    'required' => $f->required,
                    'config' => $f->config ?: [],
                    'alert' => $f->alert ?: ['enabled' => false],
                ])->values(),
            ]);

        return Inertia::render('ServiceProtocols/Index', [
            'types' => VehicleType::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->pluck('name'),
            'models' => VehicleModel::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')
                ->get(['id', 'name'])->map(fn (VehicleModel $m) => ['id' => $m->id, 'name' => $m->name])->values(),
            'vehicles' => Vehicle::query()->orderBy('callsign')->orderBy('name')
                ->get(['id', 'name', 'callsign', 'type', 'vehicle_model_id'])
                ->map(fn (Vehicle $v) => [
                    'id' => $v->id,
                    'name' => $v->callsign ?: $v->name,
                    'type' => $v->type,
                    'vehicle_model_id' => $v->vehicle_model_id,
                ])->values(),
            'phases' => ProtocolPhase::options(),
            'fieldTypes' => ProtocolFieldType::options(),
            'severities' => collect(Severity::cases())->map(fn (Severity $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'protocols' => $protocols,
            'status' => session('status'),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'vehicle_model_id' => ['nullable', 'integer'],
            'vehicle_id' => ['nullable', 'integer'],
            'phase' => ['required', Rule::enum(ProtocolPhase::class)],
            'fields' => ['array'],
        ]);

        $scope = $this->resolveScope($validated);

        DB::transaction(function () use ($scope, $validated, $request) {
            $fields = $this->sanitizeFields($request->input('fields', []));

            $existing = ServiceProtocol::query()
                ->where('phase', $validated['phase'])
                ->where('vehicle_type', $scope['vehicle_type'])
                ->where('vehicle_model_id', $scope['vehicle_model_id'])
                ->where('vehicle_id', $scope['vehicle_id'])
                ->first();

            // Aucun champ → on supprime le protocole (revient au niveau au-dessus).
            if ($fields === []) {
                $existing?->delete();

                return;
            }

            $protocol = $existing ?? ServiceProtocol::create($scope + [
                'phase' => $validated['phase'],
                'is_active' => true,
            ]);
            $protocol->update(['is_active' => true]);

            $protocol->fields()->delete();
            foreach ($fields as $order => $field) {
                $protocol->fields()->create($field + ['display_order' => $order]);
            }
        });

        return back()->with('status', 'Protocole enregistré.');
    }

    /**
     * Normalise la portée demandée en une seule cible valide, du plus précis au
     * plus général : véhicule → modèle → type → défaut. Les cibles inexistantes
     * sont ignorées (on retombe au niveau inférieur).
     *
     * @param  array<string, mixed>  $validated
     * @return array{vehicle_type:?string,vehicle_model_id:?int,vehicle_id:?int}
     */
    private function resolveScope(array $validated): array
    {
        $vehicleId = $validated['vehicle_id'] ?? null;
        if ($vehicleId !== null && Vehicle::query()->whereKey($vehicleId)->exists()) {
            return ['vehicle_type' => null, 'vehicle_model_id' => null, 'vehicle_id' => (int) $vehicleId];
        }

        $modelId = $validated['vehicle_model_id'] ?? null;
        if ($modelId !== null && VehicleModel::query()->whereKey($modelId)->exists()) {
            return ['vehicle_type' => null, 'vehicle_model_id' => (int) $modelId, 'vehicle_id' => null];
        }

        $vehicleType = $validated['vehicle_type'] ?: null;
        if ($vehicleType !== null && VehicleType::query()->where('name', $vehicleType)->exists()) {
            return ['vehicle_type' => $vehicleType, 'vehicle_model_id' => null, 'vehicle_id' => null];
        }

        return ['vehicle_type' => null, 'vehicle_model_id' => null, 'vehicle_id' => null];
    }

    /**
     * Nettoie/valide les champs reçus du formulaire.
     *
     * @param  array<int, mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function sanitizeFields(array $raw): array
    {
        $validTypes = array_map(fn (ProtocolFieldType $t) => $t->value, ProtocolFieldType::cases());
        $validSeverities = array_map(fn (Severity $s) => $s->value, Severity::cases());
        $out = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $type = (string) ($item['type'] ?? '');
            if ($label === '' || ! in_array($type, $validTypes, true)) {
                continue;
            }
            $typeEnum = ProtocolFieldType::from($type);

            // Un titre de section n'est qu'un libellé : pas de config, d'alerte
            // ni d'obligation.
            if (! $typeEnum->isInput()) {
                $out[] = [
                    'label' => mb_substr($label, 0, 200),
                    'type' => $type,
                    'required' => false,
                    'config' => [],
                    'alert' => ['enabled' => false],
                ];

                continue;
            }

            // Config selon le type.
            $rawConfig = is_array($item['config'] ?? null) ? $item['config'] : [];
            $config = [];
            if ($typeEnum->isNumeric()) {
                foreach (['min', 'max', 'step'] as $k) {
                    if (isset($rawConfig[$k]) && is_numeric($rawConfig[$k])) {
                        $config[$k] = 0 + $rawConfig[$k];
                    }
                }
                $unit = trim((string) ($rawConfig['unit'] ?? ''));
                if ($unit !== '') {
                    $config['unit'] = mb_substr($unit, 0, 16);
                }
                // Valeur transmise à Itineris (lien Itineris) : niveau d'oxygène.
                if (($rawConfig['share'] ?? null) === 'o2') {
                    $config['share'] = 'o2';
                }
            } elseif ($typeEnum === ProtocolFieldType::TEXT) {
                $config['multiline'] = ! empty($rawConfig['multiline']);
                $config['uppercase'] = ! empty($rawConfig['uppercase']);
            }

            // Déclencheur d'alerte.
            $rawAlert = is_array($item['alert'] ?? null) ? $item['alert'] : [];
            $alert = ['enabled' => false];
            if (! empty($rawAlert['enabled'])) {
                $operator = (string) ($rawAlert['operator'] ?? '');
                $allowedOps = match (true) {
                    $typeEnum->isNumeric() => ['lt', 'lte', 'gt', 'gte', 'eq'],
                    $typeEnum === ProtocolFieldType::TRISTATE => ['is_nok'],
                    $typeEnum === ProtocolFieldType::CHECKBOX => ['unchecked', 'checked'],
                    default => [],
                };
                if (in_array($operator, $allowedOps, true)) {
                    $severity = in_array($rawAlert['severity'] ?? '', $validSeverities, true)
                        ? $rawAlert['severity'] : Severity::WARNING->value;
                    $alert = [
                        'enabled' => true,
                        'operator' => $operator,
                        'severity' => $severity,
                        'message' => mb_substr(trim((string) ($rawAlert['message'] ?? '')), 0, 200),
                    ];
                    if ($typeEnum->isNumeric() && isset($rawAlert['threshold']) && is_numeric($rawAlert['threshold'])) {
                        $alert['threshold'] = 0 + $rawAlert['threshold'];
                    }
                    // Jauge : l'alerte peut porter sur la valeur ou le pourcentage.
                    if ($typeEnum === ProtocolFieldType::GAUGE) {
                        $alert['basis'] = ($rawAlert['basis'] ?? 'value') === 'percent' ? 'percent' : 'value';
                    }
                }
            }

            $out[] = [
                'label' => mb_substr($label, 0, 200),
                'type' => $type,
                'required' => ! empty($item['required']),
                'config' => $config,
                'alert' => $alert,
            ];
        }

        return $out;
    }
}
