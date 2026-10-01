<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\VehicleInventory;
use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sortie de consommables depuis l'inventaire d'un véhicule, et réarmement
 * (remise au niveau théorique) — accessible à l'équipage en service ou à un
 * gestionnaire.
 */
class MaterialConsumptionController extends Controller
{
    /** Enregistre une sortie de consommable (décrémente le stock du véhicule). */
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorizeCrew($request, $vehicle, $session);

        $validated = $request->validate([
            'material_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'material_item_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $material = $this->vehicleMaterial($vehicle, (int) $validated['material_id']);

        $item = null;
        if (! empty($validated['material_item_id'])) {
            $item = MaterialItem::query()
                ->where('material_id', $material->id)
                ->whereKey((int) $validated['material_item_id'])
                ->first();
        }

        VehicleInventory::consume(
            $vehicle,
            $material,
            (int) $validated['quantity'],
            $item,
            $request->user(),
            $session,
            $validated['notes'] ?? null,
        );

        return back()->with('status', 'Sortie de consommable enregistrée.');
    }

    /** Réarme (pointage) un ou plusieurs matériels : remise au niveau théorique. */
    public function restock(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorizeCrew($request, $vehicle, $session);

        $validated = $request->validate([
            'material_ids' => ['array'],
            'material_ids.*' => ['integer'],
        ]);

        $locationIds = Location::query()->where('vehicle_id', $vehicle->id)->pluck('id');
        Material::query()
            ->whereIn('id', $validated['material_ids'] ?? [])
            ->whereIn('location_id', $locationIds)
            ->get()
            ->each(fn (Material $m) => VehicleInventory::restock($m));

        return back()->with('status', 'Réarmement enregistré.');
    }

    /** Vérifie que l'utilisateur est équipage de la session ouverte (ou gestionnaire). */
    private function authorizeCrew(Request $request, Vehicle $vehicle, ?VehicleSession &$session): void
    {
        $session = VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->first();

        abort_unless(
            ($session !== null && $session->involves($request->user()->id)) || $request->user()->can('vehicles.manage'),
            403,
        );
    }

    /** Récupère un matériel appartenant bien à un emplacement du véhicule. */
    private function vehicleMaterial(Vehicle $vehicle, int $materialId): Material
    {
        $locationIds = Location::query()->where('vehicle_id', $vehicle->id)->pluck('id');

        $material = Material::query()->whereKey($materialId)->first();
        abort_unless($material !== null && $material->location_id !== null && $locationIds->contains($material->location_id), 404);

        return $material;
    }
}
