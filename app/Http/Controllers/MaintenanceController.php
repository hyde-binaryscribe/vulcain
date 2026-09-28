<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\MaintenanceType;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    /** Enregistre une opération de suivi mécanique pour un véhicule. */
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(MaintenanceType::class)],
            'performed_at' => ['required', 'date', 'before_or_equal:today'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'provider' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_due_at' => ['nullable', 'date'],
            'next_due_mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);

        $vehicle->maintenances()->create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'performed_at' => $validated['performed_at'],
            'mileage' => $validated['mileage'] ?? null,
            'cost' => $validated['cost'] ?? null,
            'provider' => $validated['provider'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'next_due_at' => $validated['next_due_at'] ?? null,
            'next_due_mileage' => $validated['next_due_mileage'] ?? null,
        ]);

        // Met à jour le compteur du véhicule si le relevé est plus récent (plus élevé).
        if (! empty($validated['mileage']) && $validated['mileage'] > (int) $vehicle->mileage) {
            $vehicle->update(['mileage' => $validated['mileage']]);
        }

        return back()->with('status', 'Opération d’entretien enregistrée.');
    }

    /** Met à jour le compteur kilométrique courant du véhicule (déclenche les alertes km). */
    public function updateMileage(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'mileage' => ['required', 'integer', 'min:0', 'max:9999999'],
        ]);

        $vehicle->update(['mileage' => $validated['mileage']]);

        return back()->with('status', 'Kilométrage mis à jour.');
    }

    /** Supprime une entrée du suivi mécanique (correction). */
    public function destroy(Vehicle $vehicle, MaintenanceRecord $maintenance): RedirectResponse
    {
        abort_unless($maintenance->vehicle_id === $vehicle->id, 404);

        $maintenance->delete();

        return back()->with('status', 'Entrée d’entretien supprimée.');
    }
}
