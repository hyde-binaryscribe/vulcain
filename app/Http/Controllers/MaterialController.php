<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\MaterialStatus;
use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialItem;
use App\Models\MaterialType;
use App\Models\StockLot;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $materials = Material::query()
            ->with(['category:id,name', 'type:id,name,tracking_mode', 'location:id,name,parent_id,vehicle_id', 'location.vehicle:id,name', 'location.parent:id,name,parent_id,vehicle_id'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (Material $m) => [
                'id' => $m->id,
                'reference' => $m->reference,
                'name' => $m->name,
                'brand' => $m->brand,
                'description' => $m->description,
                'category' => $m->category?->name,
                'category_id' => $m->category_id,
                'type' => $m->type?->name,
                'material_type_id' => $m->material_type_id,
                'location' => $m->location?->fullPath(),
                'location_id' => $m->location_id,
                'tracking_mode' => $m->tracking_mode,
                'theoretical_qty' => $m->theoretical_qty,
                'minimum_qty' => $m->minimum_qty,
                'serial_number' => $m->serial_number,
                'expiry_date' => $m->expiry_date?->format('Y-m-d'),
                'next_check_date' => $m->next_check_date?->format('Y-m-d'),
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'observations' => $m->observations,
            ]);

        return Inertia::render('Materials/Index', [
            'materials' => $materials,
            'categories' => MaterialCategory::query()->orderBy('name')->get(['id', 'name']),
            'materialTypes' => MaterialType::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['id', 'name', 'tracking_mode']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => MaterialStatus::options(),
            'trackingModes' => collect(Material::TRACKING_MODES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'search' => $search,
            'status' => session('status'),
        ]);
    }

    public function show(Material $material): Response
    {
        $material->load(['category:id,name', 'type:id,name', 'location:id,name,parent_id,vehicle_id', 'location.vehicle:id,name', 'location.parent:id,name,parent_id,vehicle_id']);

        $itemLocationLoad = ['location:id,name,parent_id,vehicle_id', 'location.vehicle:id,name', 'location.parent:id,name,parent_id,vehicle_id'];

        $items = $material->tracking_mode === Material::MODE_SERIAL
            ? $material->items()->with($itemLocationLoad)->orderBy('serial_number')->get()->map(fn ($i) => [
                'id' => $i->id,
                'serial_number' => $i->serial_number,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'location' => $i->location?->fullPath(),
                'location_id' => $i->location_id,
                'next_check_date' => $i->next_check_date?->format('Y-m-d'),
                'notes' => $i->notes,
            ])
            : [];

        $lots = $material->tracking_mode === Material::MODE_LOT
            ? $material->lots()->with($itemLocationLoad)->orderByRaw('expiry_date is null')->orderBy('expiry_date')->get()->map(fn ($l) => [
                'id' => $l->id,
                'lot_number' => $l->lot_number,
                'quantity' => $l->quantity,
                'expiry_date' => $l->expiry_date?->format('Y-m-d'),
                'expired' => $l->isExpired(),
                'expiring_soon' => $l->expiresWithin(30),
                'status' => $l->status->value,
                'status_label' => $l->status->label(),
                'location' => $l->location?->fullPath(),
                'location_id' => $l->location_id,
            ])
            : [];

        return Inertia::render('Materials/Show', [
            'material' => [
                'id' => $material->id,
                'name' => $material->name,
                'reference' => $material->reference,
                'description' => $material->description,
                'category' => $material->category?->name,
                'location' => $material->location?->name,
                'tracking_mode' => $material->tracking_mode,
                'tracking_label' => Material::TRACKING_MODES[$material->tracking_mode] ?? $material->tracking_mode,
                'theoretical_qty' => $material->theoretical_qty,
                'minimum_qty' => $material->minimum_qty,
                'current_qty' => $material->current_qty,
                'stock' => $material->stockQuantity(),
                'below_threshold' => $material->isBelowThreshold(),
                'status' => $material->status->value,
                'status_label' => $material->status->label(),
            ],
            'items' => $items,
            'lots' => $lots,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => MaterialStatus::options(),
            'history' => $this->historyFor($material),
            'status' => session('status'),
        ]);
    }

    /**
     * Historique du matériel et de ses exemplaires / lots.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function historyFor(Material $material)
    {
        $itemIds = MaterialItem::query()->where('material_id', $material->id)->pluck('id')->all();
        $lotIds = StockLot::query()->where('material_id', $material->id)->pluck('id')->all();

        return ActivityLog::query()
            ->forSubjects([
                Material::class => [$material->id],
                MaterialItem::class => $itemIds,
                StockLot::class => $lotIds,
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (ActivityLog $l) => ActivityController::format($l));
    }

    public function setStock(Request $request, Material $material): RedirectResponse
    {
        $validated = $request->validate([
            'current_qty' => ['required', 'integer', 'min:0'],
        ]);

        $material->update(['current_qty' => $validated['current_qty']]);

        return back()->with('status', 'Stock mis à jour.');
    }

    public function store(Request $request): RedirectResponse
    {
        Material::create($this->validated($request));

        return back()->with('status', 'Matériel ajouté.');
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $material->update($this->validated($request));

        return back()->with('status', 'Matériel mis à jour.');
    }

    public function quickStatus(Request $request, Material $material): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(MaterialStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $material->status = $validated['status'];
        if (! empty($validated['note'])) {
            $material->observations = $validated['note'];
        }
        $material->save();

        return back()->with('status', 'Statut mis à jour.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $material->delete();

        return back()->with('status', 'Matériel supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $orgId = $this->tenant->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', Rule::exists('material_categories', 'id')->where('organisation_id', $orgId)],
            'material_type_id' => ['nullable', Rule::exists('material_types', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            // Le mode de suivi est imposé par le type quand il est renseigné ;
            // sinon il reste saisi directement (compatibilité).
            'tracking_mode' => ['required_without:material_type_id', 'nullable', Rule::in(array_keys(Material::TRACKING_MODES))],
            'theoretical_qty' => ['nullable', 'integer', 'min:0'],
            'minimum_qty' => ['nullable', 'integer', 'min:0'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'next_check_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(MaterialStatus::class)],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        // Un type sélectionné impose son mode de suivi au modèle.
        if (! empty($data['material_type_id'])) {
            $mode = MaterialType::query()->whereKey($data['material_type_id'])->value('tracking_mode');
            if ($mode !== null) {
                $data['tracking_mode'] = $mode;
            }
        }
        $data['tracking_mode'] ??= Material::MODE_QUANTITY;

        $data['theoretical_qty'] ??= 0;
        $data['minimum_qty'] ??= 0;

        return $data;
    }
}
