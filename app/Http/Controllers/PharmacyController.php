<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Espace pharmacie : déclaration rapide des consommables (matériel suivi par
 * lot / péremption). La gestion administrative complète viendra plus tard.
 */
class PharmacyController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $today = Carbon::today();

        $consumables = Material::query()
            ->where('tracking_mode', Material::MODE_LOT)
            ->with(['category:id,name', 'location:id,name,parent_id,vehicle_id', 'location.vehicle:id,name', 'location.parent:id,name,parent_id,vehicle_id', 'lots:id,material_id,quantity,expiry_date'])
            ->orderBy('name')
            ->get()
            ->map(function (Material $m) use ($today) {
                $nearest = $m->lots->pluck('expiry_date')->filter()->sort()->first();

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'reference' => $m->reference,
                    'category' => $m->category?->name,
                    'location' => $m->location?->fullPath(),
                    'stock' => $m->stockQuantity(),
                    'minimum_qty' => $m->minimum_qty,
                    'below_threshold' => $m->isBelowThreshold(),
                    'nearest_expiry' => $nearest?->format('d/m/Y'),
                    'nearest_expiry_sort' => $nearest?->format('Y-m-d'),
                    'expired' => $nearest !== null && $nearest->lt($today),
                    'expiring_soon' => $nearest !== null && $nearest->gte($today) && $nearest->lte($today->copy()->addDays(30)),
                ];
            })
            // FEFO : péremption la plus proche d'abord (sans date en dernier).
            ->sortBy(fn ($c) => $c['nearest_expiry_sort'] ?? '9999-99-99')
            ->values();

        return Inertia::render('Pharmacy/Index', [
            'consumables' => $consumables,
            'categories' => MaterialCategory::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->where('is_active', true)
                ->with(['vehicle:id,name', 'parent:id,name,parent_id,vehicle_id'])
                ->orderBy('name')->get()
                ->map(fn (Location $l) => ['id' => $l->id, 'name' => $l->fullPath()]),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $orgId = $this->tenant->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', Rule::exists('material_categories', 'id')->where('organisation_id', $orgId)],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'minimum_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        Material::create([
            'name' => $validated['name'],
            'reference' => $validated['reference'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'tracking_mode' => Material::MODE_LOT,
            'minimum_qty' => $validated['minimum_qty'] ?? 0,
            'status' => MaterialStatus::CONFORME->value,
        ]);

        return back()->with('status', 'Consommable déclaré. Ajoute ses lots (quantité / péremption) depuis sa fiche matériel.');
    }
}
