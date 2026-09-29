<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tâches persistantes d'un véhicule : création/suppression par un responsable,
 * réalisation (coche) par l'agent sur le terrain.
 */
class VehicleTaskController extends Controller
{
    /** Ajoute une tâche au véhicule (responsable). */
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $vehicle->tasks()->create([
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', 'Tâche ajoutée.');
    }

    /** Marque une tâche comme faite (agent). */
    public function complete(Request $request, Vehicle $vehicle, VehicleTask $task): RedirectResponse
    {
        abort_unless($task->vehicle_id === $vehicle->id, 404);

        if ($task->done_at === null) {
            $task->update(['done_at' => now(), 'done_by' => $request->user()->id]);
        }

        return back()->with('status', 'Tâche réalisée.');
    }

    /** Ré-ouvre une tâche (responsable). */
    public function reopen(Vehicle $vehicle, VehicleTask $task): RedirectResponse
    {
        abort_unless($task->vehicle_id === $vehicle->id, 404);

        $task->update(['done_at' => null, 'done_by' => null]);

        return back()->with('status', 'Tâche ré-ouverte.');
    }

    public function destroy(Vehicle $vehicle, VehicleTask $task): RedirectResponse
    {
        abort_unless($task->vehicle_id === $vehicle->id, 404);

        $task->delete();

        return back()->with('status', 'Tâche supprimée.');
    }
}
