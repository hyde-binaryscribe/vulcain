<?php

namespace App\Http\Controllers;

use App\Domain\Protocol\ProtocolItemState;
use App\Domain\Protocol\ProtocolScope;
use App\Domain\Protocol\ProtocolSerialState;
use App\Domain\Protocol\ProtocolSnapshot;
use App\Models\Protocol;
use App\Models\ProtocolItem;
use App\Models\ProtocolTemplate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProtocolController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ProtocolScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $protocols = Protocol::query()
            ->with(['verifier:id,name'])
            ->orderByDesc('started_at')
            ->limit(100)
            ->get()
            ->map(fn (Protocol $i) => [
                'id' => $i->id,
                'vehicle_name' => $i->vehicle_name,
                'template_name' => $i->template_name,
                'type_labels' => $i->typeLabels(),
                'verifier' => $i->verifier?->name,
                'status' => $i->status,
                'started_at' => $i->started_at?->format('d/m/Y H:i'),
                'validated_at' => $i->validated_at?->format('d/m/Y H:i'),
                'is_owner' => $i->user_id === $request->user()->id,
            ]);

        // Modèles actifs disponibles pour démarrer un protocole.
        $templates = ProtocolTemplate::query()
            ->where('is_active', true)
            ->with('vehicle:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (ProtocolTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'vehicle' => $this->scope->targetLabel($t),
                'type_labels' => $t->typeLabels(),
            ]);

        return Inertia::render('Protocols/Index', [
            'protocols' => $protocols,
            'templates' => $templates,
            'canManage' => $request->user()->can('protocols.manage'),
            'status' => session('status'),
        ]);
    }

    public function start(Request $request, ProtocolSnapshot $snapshot): RedirectResponse
    {
        $validated = $request->validate([
            'protocol_template_id' => [
                'required',
                Rule::exists('protocol_templates', 'id')->where('organisation_id', $this->tenant->id())->whereNull('deleted_at'),
            ],
        ]);

        $template = ProtocolTemplate::findOrFail($validated['protocol_template_id']);
        $vehicle = $this->scope->vehicle($template);

        // Vérificateur : uniquement sur ses véhicules autorisés (les protocoles
        // sur emplacement fixe / dépôt sont réservés aux gestionnaires).
        if (! $request->user()->can('protocols.manage')) {
            abort_if($vehicle === null, 403, 'Ce protocole nécessite un gestionnaire.');
            abort_unless(
                $vehicle->users()->whereKey($request->user()->id)->exists(),
                403,
                'Vous n’êtes pas autorisé sur ce véhicule.'
            );
        }

        $protocol = $snapshot->start($template, $request->user());

        return redirect()->route('protocols.show', $protocol);
    }

    public function show(Request $request, Protocol $protocol): Response
    {
        $this->authorizeView($request, $protocol);

        $protocol->load('verifier:id,name');

        $groups = $protocol->items
            ->groupBy(fn (ProtocolItem $i) => $i->location_name ?: 'Sans emplacement')
            ->map(fn ($items, $location) => [
                'location' => $location,
                'items' => $items->map(fn (ProtocolItem $i) => [
                    'id' => $i->id,
                    'material_name' => $i->material_name,
                    'reference' => $i->reference,
                    'tracking_mode' => $i->tracking_mode,
                    'serial_number' => $i->serial_number,
                    'expected_qty' => $i->expected_qty,
                    'observed_qty' => $i->observed_qty,
                    'last_known_expiry' => $i->last_known_expiry?->toDateString(),
                    'observed_expiry' => $i->observed_expiry?->toDateString(),
                    'expiry_required' => $i->expiry_required,
                    'state' => $i->state,
                    'observation' => $i->observation,
                    'checked' => $i->checked,
                    'photo_required' => $i->photo_required,
                    'photo_url' => $i->photo_path ? Storage::disk('public')->url($i->photo_path) : null,
                    'row_version' => $i->row_version,
                ])->values(),
            ])->values();

        $total = $protocol->items->count();
        $checked = $protocol->items->where('checked', true)->count();

        return Inertia::render('Protocols/Show', [
            'protocol' => [
                'id' => $protocol->id,
                'vehicle_name' => $protocol->vehicle_name,
                'template_name' => $protocol->template_name,
                'type_labels' => $protocol->typeLabels(),
                'verifier' => $protocol->verifier?->name,
                'status' => $protocol->status,
                'started_at' => $protocol->started_at?->format('d/m/Y H:i'),
                'validated_at' => $protocol->validated_at?->format('d/m/Y H:i'),
                'editable' => $protocol->isDraft() && $this->canEdit($request, $protocol),
            ],
            'groups' => $groups,
            'progress' => ['checked' => $checked, 'total' => $total],
            'states' => ProtocolItemState::options(),
            'serialStates' => ProtocolSerialState::options(),
            'status' => session('status'),
        ]);
    }

    public function updateItem(Request $request, Protocol $protocol, int $item): RedirectResponse
    {
        abort_unless($protocol->isDraft() && $this->canEdit($request, $protocol), 403);

        $protocolItem = $protocol->items()->findOrFail($item);

        $stateRule = $protocolItem->isSerial()
            ? Rule::enum(ProtocolSerialState::class)
            : Rule::enum(ProtocolItemState::class);

        $validated = $request->validate([
            'observed_qty' => ['nullable', 'integer', 'min:0'],
            'observed_expiry' => ['nullable', 'date'],
            'state' => ['nullable', $stateRule],
            'observation' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $changes = [
            'observed_qty' => $validated['observed_qty'] ?? null,
            'state' => $validated['state'] ?? null,
            'observation' => $validated['observation'] ?? null,
            'checked' => true,
            'row_version' => $protocolItem->row_version + 1,
        ];

        // Péremption relevée (consommable uniquement).
        if ($protocolItem->isLot()) {
            $changes['observed_expiry'] = $validated['observed_expiry'] ?? null;
        }

        // Photo facultative (anomalie série) — stockée sur le disque public.
        if ($request->hasFile('photo')) {
            $changes['photo_path'] = $request->file('photo')->store('protocol-photos', 'public');
        }

        $protocolItem->update($changes);

        return back(303)->with('status', 'Enregistré.');
    }

    private function authorizeView(Request $request, Protocol $protocol): void
    {
        // Un brouillon d'un autre vérificateur n'est pas consultable (sauf gestion).
        if ($protocol->isDraft()
            && ! $request->user()->can('protocols.manage')
            && $protocol->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function canEdit(Request $request, Protocol $protocol): bool
    {
        return $request->user()->can('protocols.manage')
            || $protocol->user_id === $request->user()->id;
    }
}
