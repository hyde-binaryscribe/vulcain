<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\ProtocolFieldType;
use App\Domain\Fleet\ProtocolPhase;
use App\Domain\Support\Severity;
use App\Models\ServiceProtocol;
use App\Models\ServiceProtocolField;
use App\Models\VehicleType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Constructeur des protocoles de service (ouverture / fermeture) par type de
 * véhicule : champs typés et déclencheurs d'alerte.
 */
class ServiceProtocolController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        VehicleType::ensureSeeded($this->tenant->organisation());

        $protocols = ServiceProtocol::query()->with('fields')->get()
            ->map(fn (ServiceProtocol $p) => [
                'vehicle_type' => $p->vehicle_type,
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
            'phase' => ['required', Rule::enum(ProtocolPhase::class)],
            'fields' => ['array'],
        ]);

        $vehicleType = $validated['vehicle_type'] ?: null;

        // Un type précisé doit exister au catalogue (sinon on retombe sur « défaut »).
        if ($vehicleType !== null && ! VehicleType::query()->where('name', $vehicleType)->exists()) {
            $vehicleType = null;
        }

        DB::transaction(function () use ($vehicleType, $validated, $request) {
            $fields = $this->sanitizeFields($request->input('fields', []));

            $existing = ServiceProtocol::query()
                ->where('phase', $validated['phase'])
                ->where('vehicle_type', $vehicleType)
                ->first();

            // Aucun champ → on supprime le protocole (revient au comportement par défaut).
            if ($fields === []) {
                $existing?->delete();

                return;
            }

            $protocol = $existing ?? ServiceProtocol::create([
                'vehicle_type' => $vehicleType,
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
            } elseif ($typeEnum === ProtocolFieldType::TEXT) {
                $config['multiline'] = ! empty($rawConfig['multiline']);
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
