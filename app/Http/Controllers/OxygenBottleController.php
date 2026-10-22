<?php

namespace App\Http\Controllers;

use App\Models\OxygenBottle;
use App\Models\OxygenBottleLog;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Oxygène embarqué. Le gestionnaire déclare la dotation (emplacements de
 * bouteille) ; en service, l'équipage ajuste le niveau et pointe les
 * changements de bouteille. Toute action terrain est tracée (OxygenBottleLog).
 */
class OxygenBottleController extends Controller
{
    // --- Gestionnaire : dotation (déclaration des emplacements) ---------------

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $this->validateBottle($request);

        $pressure = $data['current_pressure'] ?? $data['pressure_max'];
        $order = (int) $vehicle->oxygenBottles()->max('display_order') + 1;

        $vehicle->oxygenBottles()->create($data + [
            'current_pressure' => $pressure,
            'status' => OxygenBottle::STATUS_EN_SERVICE,
            'is_active' => true,
            'display_order' => $order,
            'last_checked_at' => now(),
        ]);

        return back()->with('status', 'Bouteille ajoutée.');
    }

    public function update(Request $request, Vehicle $vehicle, OxygenBottle $bottle): RedirectResponse
    {
        abort_unless($bottle->vehicle_id === $vehicle->id, 404);

        $data = $this->validateBottle($request);
        // La pression courante reste gérée par le terrain ; ici on ne force rien
        // si elle n'est pas fournie explicitement.
        if (($data['current_pressure'] ?? null) === null) {
            unset($data['current_pressure']);
        }

        $bottle->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('status', 'Bouteille mise à jour.');
    }

    public function destroy(Vehicle $vehicle, OxygenBottle $bottle): RedirectResponse
    {
        abort_unless($bottle->vehicle_id === $vehicle->id, 404);

        $bottle->delete();

        return back()->with('status', 'Bouteille retirée de la dotation.');
    }

    // --- Terrain (équipage en service ou gestionnaire) ------------------------

    /** Ajuste le niveau courant d'une bouteille (relevé tracé). */
    public function reading(Request $request, Vehicle $vehicle, OxygenBottle $bottle): RedirectResponse
    {
        $this->authorizeCrew($request, $vehicle);
        abort_unless($bottle->vehicle_id === $vehicle->id, 404);

        $validated = $request->validate([
            'pressure' => ['required', 'integer', 'min:0', 'max:'.max(1, $bottle->pressure_max)],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $pressure = (int) $validated['pressure'];
        $percent = OxygenBottle::percentOf($pressure, $bottle->pressure_min, $bottle->pressure_max);

        DB::transaction(function () use ($bottle, $vehicle, $request, $validated, $pressure, $percent) {
            $bottle->update(['current_pressure' => $pressure, 'last_checked_at' => now()]);
            $this->log($bottle, $vehicle, $request, OxygenBottleLog::ACTION_RELEVE, [
                'pressure' => $pressure,
                'percent' => $percent,
                'note' => $validated['note'] ?? null,
            ]);
        });

        return back()->with('status', 'Niveau d\'oxygène mis à jour.');
    }

    /** Pointe un changement de bouteille : nouveau n° de série, remise à plein. */
    public function replace(Request $request, Vehicle $vehicle, OxygenBottle $bottle): RedirectResponse
    {
        $this->authorizeCrew($request, $vehicle);
        abort_unless($bottle->vehicle_id === $vehicle->id, 404);

        $validated = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:80'],
            'pressure' => ['nullable', 'integer', 'min:0', 'max:'.max(1, $bottle->pressure_max)],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $previousSerial = $bottle->serial_number;
        $serial = isset($validated['serial_number']) ? strtoupper(trim($validated['serial_number'])) : null;
        $pressure = $validated['pressure'] ?? $bottle->pressure_max; // remise à plein par défaut
        $percent = OxygenBottle::percentOf((int) $pressure, $bottle->pressure_min, $bottle->pressure_max);

        DB::transaction(function () use ($bottle, $vehicle, $request, $serial, $previousSerial, $pressure, $percent, $validated) {
            $bottle->update([
                'serial_number' => $serial ?: null,
                'current_pressure' => (int) $pressure,
                'status' => OxygenBottle::STATUS_EN_SERVICE,
                'last_checked_at' => now(),
            ]);
            $this->log($bottle, $vehicle, $request, OxygenBottleLog::ACTION_REMPLACEMENT, [
                'pressure' => (int) $pressure,
                'percent' => $percent,
                'serial_number' => $serial ?: null,
                'previous_serial' => $previousSerial,
                'note' => $validated['note'] ?? null,
            ]);
        });

        return back()->with('status', 'Changement de bouteille enregistré.');
    }

    /** Change le statut d'une bouteille (à remplacer / hors service / en service). */
    public function status(Request $request, Vehicle $vehicle, OxygenBottle $bottle): RedirectResponse
    {
        $this->authorizeCrew($request, $vehicle);
        abort_unless($bottle->vehicle_id === $vehicle->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(OxygenBottle::statuses())],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        DB::transaction(function () use ($bottle, $vehicle, $request, $validated) {
            $bottle->update(['status' => $validated['status']]);
            $this->log($bottle, $vehicle, $request, OxygenBottleLog::ACTION_STATUT, [
                'note' => $validated['note'] ?? $bottle->statusLabel(),
            ]);
        });

        return back()->with('status', 'Statut de la bouteille mis à jour.');
    }

    // --- Interne --------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(OxygenBottle $bottle, Vehicle $vehicle, Request $request, string $action, array $extra): void
    {
        OxygenBottleLog::create($extra + [
            'oxygen_bottle_id' => $bottle->id,
            'vehicle_id' => $vehicle->id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'recorded_at' => now(),
        ]);
    }

    /** Action terrain autorisée : équipage de la session ouverte OU gestionnaire. */
    private function authorizeCrew(Request $request, Vehicle $vehicle): void
    {
        $user = $request->user();
        if ($user->can('vehicles.manage')) {
            return;
        }
        $open = VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->first();
        abort_unless($open !== null && $open->involves($user->id), 403);
    }

    /**
     * Règles communes à la création/mise à jour d'un emplacement de bouteille.
     *
     * @return array<string, mixed>
     */
    private function validateBottle(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'capacity_l' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'pressure_min' => ['required', 'integer', 'min:0', 'max:999'],
            'pressure_max' => ['required', 'integer', 'min:1', 'max:1000', 'gt:pressure_min'],
            'current_pressure' => ['nullable', 'integer', 'min:0'],
        ]);

        $serial = isset($validated['serial_number']) ? strtoupper(trim($validated['serial_number'])) : null;
        $validated['serial_number'] = $serial ?: null;

        return $validated;
    }
}
