<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryItemState;
use App\Domain\Inventory\InventorySnapshot;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\InventoryTemplate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): Response
    {
        $inventories = Inventory::query()
            ->with(['verifier:id,name'])
            ->orderByDesc('started_at')
            ->limit(100)
            ->get()
            ->map(fn (Inventory $i) => [
                'id' => $i->id,
                'vehicle_name' => $i->vehicle_name,
                'template_name' => $i->template_name,
                'verifier' => $i->verifier?->name,
                'status' => $i->status,
                'started_at' => $i->started_at?->format('d/m/Y H:i'),
                'validated_at' => $i->validated_at?->format('d/m/Y H:i'),
                'is_owner' => $i->user_id === $request->user()->id,
            ]);

        // Modèles actifs disponibles pour démarrer un inventaire.
        $templates = InventoryTemplate::query()
            ->where('is_active', true)
            ->with('vehicle:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'vehicle' => $t->vehicle?->name,
            ]);

        return Inertia::render('Inventories/Index', [
            'inventories' => $inventories,
            'templates' => $templates,
            'canManage' => $request->user()->can('inventories.manage'),
            'status' => session('status'),
        ]);
    }

    public function start(Request $request, InventorySnapshot $snapshot): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_template_id' => [
                'required',
                Rule::exists('inventory_templates', 'id')->where('organisation_id', $this->tenant->id())->whereNull('deleted_at'),
            ],
        ]);

        $template = InventoryTemplate::with('vehicle')->findOrFail($validated['inventory_template_id']);
        $vehicle = $template->vehicle;

        // Vérificateur : uniquement sur ses véhicules autorisés.
        if (! $request->user()->can('inventories.manage')) {
            abort_unless(
                $vehicle->users()->whereKey($request->user()->id)->exists(),
                403,
                'Vous n’êtes pas autorisé sur ce véhicule.'
            );
        }

        $inventory = $snapshot->start($vehicle, $template, $request->user());

        return redirect()->route('inventories.show', $inventory);
    }

    public function show(Request $request, Inventory $inventory): Response
    {
        $this->authorizeView($request, $inventory);

        $inventory->load('verifier:id,name');

        $groups = $inventory->items
            ->groupBy(fn (InventoryItem $i) => $i->location_name ?: 'Sans emplacement')
            ->map(fn ($items, $location) => [
                'location' => $location,
                'items' => $items->map(fn (InventoryItem $i) => [
                    'id' => $i->id,
                    'material_name' => $i->material_name,
                    'reference' => $i->reference,
                    'tracking_mode' => $i->tracking_mode,
                    'expected_qty' => $i->expected_qty,
                    'observed_qty' => $i->observed_qty,
                    'state' => $i->state?->value,
                    'observation' => $i->observation,
                    'checked' => $i->checked,
                    'photo_required' => $i->photo_required,
                    'row_version' => $i->row_version,
                ])->values(),
            ])->values();

        $total = $inventory->items->count();
        $checked = $inventory->items->where('checked', true)->count();

        return Inertia::render('Inventories/Show', [
            'inventory' => [
                'id' => $inventory->id,
                'vehicle_name' => $inventory->vehicle_name,
                'template_name' => $inventory->template_name,
                'verifier' => $inventory->verifier?->name,
                'status' => $inventory->status,
                'started_at' => $inventory->started_at?->format('d/m/Y H:i'),
                'validated_at' => $inventory->validated_at?->format('d/m/Y H:i'),
                'editable' => $inventory->isDraft() && $this->canEdit($request, $inventory),
            ],
            'groups' => $groups,
            'progress' => ['checked' => $checked, 'total' => $total],
            'states' => InventoryItemState::options(),
            'status' => session('status'),
        ]);
    }

    public function updateItem(Request $request, Inventory $inventory, int $item): RedirectResponse
    {
        abort_unless($inventory->isDraft() && $this->canEdit($request, $inventory), 403);

        $inventoryItem = $inventory->items()->findOrFail($item);

        $validated = $request->validate([
            'observed_qty' => ['nullable', 'integer', 'min:0'],
            'state' => ['nullable', Rule::enum(InventoryItemState::class)],
            'observation' => ['nullable', 'string', 'max:2000'],
        ]);

        $inventoryItem->update([
            'observed_qty' => $validated['observed_qty'] ?? null,
            'state' => $validated['state'] ?? null,
            'observation' => $validated['observation'] ?? null,
            'checked' => true,
            'row_version' => $inventoryItem->row_version + 1,
        ]);

        return back(303)->with('status', 'Enregistré.');
    }

    private function authorizeView(Request $request, Inventory $inventory): void
    {
        // Un brouillon d'un autre vérificateur n'est pas consultable (sauf gestion).
        if ($inventory->isDraft()
            && ! $request->user()->can('inventories.manage')
            && $inventory->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function canEdit(Request $request, Inventory $inventory): bool
    {
        return $request->user()->can('inventories.manage')
            || $inventory->user_id === $request->user()->id;
    }
}
