<?php

namespace App\Http\Controllers;

use App\Models\FuelRecord;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pleins de carburant : enregistrement (litrage, kilométrage, coût optionnel)
 * et suppression. Alimente le suivi de consommation.
 */
class FuelController extends Controller
{
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            // Tolérance d'un jour : l'app tourne en UTC mais le terrain envoie
            // une heure locale (fuseau FR), qui serait sinon vue « dans le futur ».
            'filled_at' => ['nullable', 'date', 'before_or_equal:'.now()->addDay()->toDateTimeString()],
            'mileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'liters' => ['required', 'numeric', 'min:0.1', 'max:9999'],
            'price_per_liter' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'full_tank' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'filled_at.before_or_equal' => 'La date du plein ne peut pas être dans le futur.',
        ]);

        // Le coût total est calculé depuis le prix au litre s'il est fourni.
        $liters = (float) $validated['liters'];
        $ppl = $validated['price_per_liter'] ?? null;
        $cost = $validated['cost'] ?? null;
        if ($ppl !== null && $cost === null) {
            $cost = round($liters * (float) $ppl, 2);
        }

        $vehicle->fuelRecords()->create([
            'user_id' => $request->user()->id,
            'filled_at' => $validated['filled_at'] ?? now(),
            'mileage' => $validated['mileage'],
            'liters' => $validated['liters'],
            'price_per_liter' => $ppl,
            'cost' => $cost,
            'full_tank' => $request->boolean('full_tank', true),
            'notes' => $validated['notes'] ?? null,
        ]);

        // Le relevé met à jour le compteur du véhicule (déclenche les alertes km).
        if ($validated['mileage'] > (int) $vehicle->mileage) {
            $vehicle->update(['mileage' => $validated['mileage']]);
        }

        return back()->with('status', 'Plein enregistré.');
    }

    public function destroy(Vehicle $vehicle, FuelRecord $fuel): RedirectResponse
    {
        abort_unless($fuel->vehicle_id === $vehicle->id, 404);

        $fuel->delete();

        return back()->with('status', 'Plein supprimé.');
    }
}
