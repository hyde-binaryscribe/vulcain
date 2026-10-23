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
use Inertia\Inertia;
use Inertia\Response;

/**
 * Oxygène embarqué. Le gestionnaire déclare la dotation (emplacements de
 * bouteille) ; en service, l'équipage ajuste le niveau et pointe les
 * changements de bouteille. Toute action terrain est tracée (OxygenBottleLog).
 */
class OxygenBottleController extends Controller
{
    /** Tableau de bord Oxygène (vue flotte, gestionnaire). */
    public function dashboard(): Response
    {
        $bottles = OxygenBottle::query()->with('vehicle:id,name,callsign')->get();
        $enService = $bottles->where('status', OxygenBottle::STATUS_EN_SERVICE);

        $percents = $enService->map(fn (OxygenBottle $b) => $b->percent())->filter(fn ($p) => $p !== null);
        $low = $enService->filter(fn (OxygenBottle $b) => ($p = $b->percent()) !== null && $p < 25)->count();

        $kpis = [
            'vehicles_equipped' => $bottles->pluck('vehicle_id')->unique()->count(),
            'bottles' => $bottles->count(),
            'low' => $low,
            'to_replace' => $bottles->where('status', OxygenBottle::STATUS_A_REMPLACER)->count(),
            'out_of_service' => $bottles->where('status', OxygenBottle::STATUS_HORS_SERVICE)->count(),
            'avg_fill' => $percents->isNotEmpty() ? (int) round($percents->avg()) : null,
        ];

        // Répartition par tranche de remplissage (bouteilles en service, avec niveau).
        $bands = [
            ['label' => '0–25 %', 'count' => 0, 'tone' => 'critical'],
            ['label' => '25–50 %', 'count' => 0, 'tone' => 'serious'],
            ['label' => '50–75 %', 'count' => 0, 'tone' => 'warning'],
            ['label' => '75–100 %', 'count' => 0, 'tone' => 'series'],
        ];
        foreach ($enService as $b) {
            $p = $b->percent();
            if ($p === null) {
                continue;
            }
            $i = $p < 25 ? 0 : ($p < 50 ? 1 : ($p < 75 ? 2 : 3));
            $bands[$i]['count']++;
        }

        $row = fn (OxygenBottle $b) => [
            'id' => $b->id,
            'vehicle_id' => $b->vehicle_id,
            'vehicle' => $b->vehicle?->callsign ?: $b->vehicle?->name,
            'label' => $b->label,
            'serial_number' => $b->serial_number,
            'pressure' => $b->current_pressure,
            'pressure_min' => $b->pressure_min,
            'pressure_max' => $b->pressure_max,
            'percent' => $b->percent(),
            'status' => $b->status,
            'status_label' => $b->statusLabel(),
            'last_checked_at' => $b->last_checked_at?->fr('d/m/Y H:i'),
        ];

        // À surveiller : hors service, à remplacer, ou niveau < 50 %. Trié par gravité.
        $statusRank = fn (OxygenBottle $b): int => match ($b->status) {
            OxygenBottle::STATUS_A_REMPLACER => 0,
            OxygenBottle::STATUS_HORS_SERVICE => 1,
            default => 2,
        };
        $attention = $bottles
            ->filter(fn (OxygenBottle $b) => $b->status !== OxygenBottle::STATUS_EN_SERVICE
                || (($p = $b->percent()) !== null && $p < 50))
            ->sortBy(fn (OxygenBottle $b) => $statusRank($b) * 1000 + ($b->percent() ?? 999))
            ->map($row)->values();

        $recent = OxygenBottleLog::query()
            ->with(['user:id,name', 'bottle:id,label', 'bottle.vehicle:id,name,callsign'])
            ->latest('recorded_at')->limit(20)->get()
            ->map(fn (OxygenBottleLog $l) => [
                'action' => $l->action,
                'action_label' => $l->actionLabel(),
                'vehicle' => $l->bottle?->vehicle?->callsign ?: $l->bottle?->vehicle?->name,
                'bottle' => $l->bottle?->label,
                'pressure' => $l->pressure,
                'percent' => $l->percent,
                'serial_number' => $l->serial_number,
                'previous_serial' => $l->previous_serial,
                'by' => $l->user?->name,
                'at' => $l->recorded_at?->fr('d/m/Y H:i'),
            ]);

        return Inertia::render('Oxygen/Dashboard', [
            'kpis' => $kpis,
            'bands' => $bands,
            'attention' => $attention,
            'recent' => $recent,
            'can_manage' => true, // page réservée aux gestionnaires (middleware)
        ]);
    }

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
