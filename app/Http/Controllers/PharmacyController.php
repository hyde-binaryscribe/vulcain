<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialConsumption;
use App\Models\MaterialType;
use App\Models\StockLot;
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
        $soon = $today->copy()->addDays($this->tenant->organisation()->expiryAlertDays());

        $consumables = Material::query()
            ->where('tracking_mode', Material::MODE_LOT)
            ->with(['category:id,name', 'type:id,name', 'location:id,name,parent_id,vehicle_id', 'location.vehicle:id,name', 'location.parent:id,name,parent_id,vehicle_id', 'lots:id,material_id,quantity,expiry_date'])
            ->orderBy('name')
            ->get()
            ->map(function (Material $m) use ($today, $soon) {
                $nearest = $m->lots->pluck('expiry_date')->filter()->sort()->first();

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'brand' => $m->brand,
                    'reference' => $m->reference,
                    'type' => $m->type?->name,
                    'category' => $m->category?->name,
                    'location' => $m->location?->fullPath(),
                    'stock' => $m->stockQuantity(),
                    'minimum_qty' => $m->minimum_qty,
                    'below_threshold' => $m->isBelowThreshold(),
                    'nearest_expiry' => $nearest?->format('d/m/Y'),
                    'nearest_expiry_sort' => $nearest?->format('Y-m-d'),
                    'expired' => $nearest !== null && $nearest->lt($today),
                    'expiring_soon' => $nearest !== null && $nearest->gte($today) && $nearest->lte($soon),
                ];
            })
            // FEFO : péremption la plus proche d'abord (sans date en dernier).
            ->sortBy(fn ($c) => $c['nearest_expiry_sort'] ?? '9999-99-99')
            ->values();

        return Inertia::render('Pharmacy/Index', [
            'consumables' => $consumables,
            'categories' => MaterialCategory::query()->orderBy('name')->get(['id', 'name']),
            // Types de matériel consommables (suivi par lot) pour la déclaration rapide.
            'materialTypes' => MaterialType::query()
                ->where('is_active', true)
                ->where('tracking_mode', Material::MODE_LOT)
                ->orderBy('display_order')->orderBy('name')
                ->get(['id', 'name']),
            'locations' => Location::query()->where('is_active', true)
                ->with(['vehicle:id,name', 'parent:id,name,parent_id,vehicle_id'])
                ->orderBy('name')->get()
                ->map(fn (Location $l) => ['id' => $l->id, 'name' => $l->fullPath()]),
            'status' => session('status'),
        ]);
    }

    /**
     * Tableau de bord pharmacie / consommables : indicateurs clés, péremptions
     * à venir (FEFO), références à réapprovisionner et journal des sorties.
     */
    public function dashboard(): Response
    {
        $today = Carbon::today();
        $alertDays = $this->tenant->organisation()->expiryAlertDays();
        $soonLimit = $today->copy()->addDays($alertDays);

        // Consommables suivis par lot (références pharmacie).
        $materials = Material::query()
            ->where('tracking_mode', Material::MODE_LOT)
            ->with(['lots:id,material_id,quantity,expiry_date', 'location:id,name,parent_id,vehicle_id', 'location.vehicle:id,name', 'location.parent:id,name,parent_id,vehicle_id'])
            ->orderBy('name')
            ->get();

        // KPI péremptions, sur les lots encore en stock.
        $lots = StockLot::query()
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date')
            ->get(['id', 'expiry_date']);

        $expired = $lots->filter(fn (StockLot $l) => $l->expiry_date->lt($today))->count();
        $soon = $lots->filter(fn (StockLot $l) => $l->expiry_date->gte($today) && $l->expiry_date->lte($soonLimit))->count();

        // Graphique « péremptions à venir » (FEFO) : périmé, bientôt, puis par mois.
        $months = [];
        foreach ($lots as $l) {
            if ($l->expiry_date->lte($soonLimit)) {
                continue;
            }
            $key = $l->expiry_date->format('Y-m');
            $months[$key] = ($months[$key] ?? 0) + 1;
        }
        ksort($months);
        $expiryChart = [
            ['label' => 'Déjà périmé', 'count' => $expired, 'tone' => 'critical'],
            ['label' => '< '.$alertDays.' j', 'count' => $soon, 'tone' => 'serious'],
        ];
        foreach (array_slice($months, 0, 4, true) as $key => $count) {
            $label = Carbon::createFromFormat('Y-m-d', $key.'-01')->translatedFormat('M Y');
            $expiryChart[] = ['label' => ucfirst($label), 'count' => $count, 'tone' => 'series'];
        }

        // À réapprovisionner : références sous le seuil mini (gravité par ratio).
        $restock = $materials
            ->filter(fn (Material $m) => $m->isBelowThreshold())
            ->map(function (Material $m) {
                $stock = $m->stockQuantity();
                $min = (int) $m->minimum_qty;
                $ratio = $min > 0 ? $stock / $min : 1;
                $tone = $ratio <= 0.25 ? 'critical' : ($ratio <= 0.5 ? 'serious' : 'warning');

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'location' => $m->location?->fullPath(),
                    'stock' => $stock,
                    'minimum_qty' => $min,
                    'tone' => $tone,
                ];
            })
            ->sortBy('stock')
            ->values();

        // Sorties récentes (consommations véhicules).
        $recent = MaterialConsumption::query()
            ->with(['material:id,name', 'vehicle:id,name,callsign', 'user:id,name'])
            ->latest('consumed_at')
            ->limit(12)
            ->get()
            ->map(fn (MaterialConsumption $c) => [
                'id' => $c->id,
                'date' => $c->consumed_at?->format('d/m H:i'),
                'material' => $c->material?->name ?? '—',
                'quantity' => $c->quantity,
                'vehicle' => $c->vehicle?->callsign ?: $c->vehicle?->name ?? '—',
                'user' => $c->user?->name ?? '—',
                'serial' => $c->serial_number,
            ]);

        return Inertia::render('Pharmacy/Dashboard', [
            'kpis' => [
                'references' => $materials->count(),
                'low_stock' => $materials->filter(fn (Material $m) => $m->isBelowThreshold())->count(),
                'expired' => $expired,
                'expiring_soon' => $soon,
            ],
            'expiryChart' => $expiryChart,
            'restock' => $restock,
            'recent' => $recent,
            'alertDays' => $alertDays,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $orgId = $this->tenant->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', Rule::exists('material_categories', 'id')->where('organisation_id', $orgId)],
            // Un type consommable (suivi par lot) peut être rattaché à la déclaration.
            'material_type_id' => ['nullable', Rule::exists('material_types', 'id')->where('organisation_id', $orgId)->where('tracking_mode', Material::MODE_LOT)->whereNull('deleted_at')],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'minimum_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        Material::create([
            'name' => $validated['name'],
            'brand' => $validated['brand'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'material_type_id' => $validated['material_type_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'tracking_mode' => Material::MODE_LOT,
            'minimum_qty' => $validated['minimum_qty'] ?? 0,
            'status' => MaterialStatus::CONFORME->value,
        ]);

        return back()->with('status', 'Consommable déclaré. Ajoute ses lots (quantité / péremption) depuis sa fiche matériel.');
    }
}
