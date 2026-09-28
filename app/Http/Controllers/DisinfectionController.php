<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\DisinfectionType;
use App\Models\DisinfectionRecord;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisinfectionController extends Controller
{
    /** Enregistre une désinfection / un nettoyage pour un véhicule. */
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(DisinfectionType::class)],
            'performed_at' => ['required', 'date', 'before_or_equal:now'],
            'disinfection_protocol_id' => ['nullable', 'integer', Rule::exists('disinfection_protocols', 'id')->where('organisation_id', $vehicle->organisation_id)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'performed_at.before_or_equal' => 'La date de désinfection ne peut pas être dans le futur.',
        ]);

        $vehicle->disinfections()->create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'disinfection_protocol_id' => $validated['disinfection_protocol_id'] ?? null,
            'performed_at' => $validated['performed_at'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', 'Désinfection enregistrée.');
    }

    /** Supprime une entrée du journal (correction). */
    public function destroy(Vehicle $vehicle, DisinfectionRecord $disinfection): RedirectResponse
    {
        abort_unless($disinfection->vehicle_id === $vehicle->id, 404);

        $disinfection->delete();

        return back()->with('status', 'Entrée de désinfection supprimée.');
    }
}
