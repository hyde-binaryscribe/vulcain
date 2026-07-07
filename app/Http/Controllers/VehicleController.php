<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\VehicleStatus;
use App\Models\Location;
use App\Models\Material;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function show(Vehicle $vehicle): Response
    {
        $vehicle->load('users:id,name');

        $locations = Location::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderBy('display_order')
            ->get();

        $materials = Material::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->with(['category:id,name', 'items', 'lots'])
            ->orderBy('name')
            ->get()
            ->map(fn (Material $m) => $this->materialSummary($m));

        $grouped = $locations->map(fn (Location $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'materials' => $materials->where('location_id', $l->id)->values(),
        ]);

        return Inertia::render('Vehicles/Show', [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'type' => $vehicle->type,
                'callsign' => $vehicle->callsign,
                'registration' => $vehicle->registration,
                'center' => $vehicle->center,
                'status' => $vehicle->status->value,
                'status_label' => $vehicle->status->label(),
                'mileage' => $vehicle->mileage,
            ],
            'assigned' => $vehicle->users->pluck('name'),
            'locations' => $grouped,
            'alerts' => [
                'expired' => $materials->where('expired', true)->count(),
                'expiring_soon' => $materials->where('expiring_soon', true)->count(),
                'below_threshold' => $materials->where('below_threshold', true)->count(),
                'anomalies' => $materials->whereNotIn('status', ['conforme'])->count(),
            ],
            'status' => session('status'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function materialSummary(Material $m): array
    {
        $stock = match ($m->tracking_mode) {
            Material::MODE_SERIAL => $m->items->count(),
            Material::MODE_LOT => (int) $m->lots->sum('quantity'),
            default => (int) $m->current_qty,
        };

        $expiry = null;
        $expired = false;
        $soon = false;

        if ($m->tracking_mode === Material::MODE_LOT) {
            $lot = $m->lots->whereNotNull('expiry_date')->sortBy('expiry_date')->first();
            if ($lot !== null) {
                $expiry = $lot->expiry_date?->format('Y-m-d');
                $expired = $lot->isExpired();
                $soon = $lot->expiresWithin(30);
            }
        }

        return [
            'id' => $m->id,
            'location_id' => $m->location_id,
            'name' => $m->name,
            'reference' => $m->reference,
            'category' => $m->category?->name,
            'tracking_mode' => $m->tracking_mode,
            'stock' => $stock,
            'theoretical_qty' => $m->theoretical_qty,
            'minimum_qty' => $m->minimum_qty,
            'below_threshold' => $m->minimum_qty > 0 && $stock < $m->minimum_qty,
            'status' => $m->status->value,
            'status_label' => $m->status->label(),
            'nearest_expiry' => $expiry,
            'expired' => $expired,
            'expiring_soon' => $soon,
        ];
    }

    public function index(): Response
    {
        $vehicles = Vehicle::query()
            ->with('users:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'callsign' => $v->callsign,
                'registration' => $v->registration,
                'center' => $v->center,
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'commissioned_at' => $v->commissioned_at?->format('Y-m-d'),
                'mileage' => $v->mileage,
                'observations' => $v->observations,
                'assigned_user_ids' => $v->users->pluck('id'),
                'assigned_names' => $v->users->pluck('name'),
            ]);

        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'grade'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'grade' => $u->grade]);

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
            'users' => $users,
            'statuses' => VehicleStatus::options(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Vehicle::create($this->validated($request));

        return back()->with('status', 'Véhicule créé.');
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($this->validated($request));

        return back()->with('status', 'Véhicule mis à jour.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete();

        return back()->with('status', 'Véhicule supprimé.');
    }

    public function assignments(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $request->validate([
            'user_ids' => ['array'],
            'user_ids.*' => ['integer'],
        ]);

        // Ne conserver que les utilisateurs de l'organisation (scope appliqué).
        $ids = User::query()->whereIn('id', $request->input('user_ids', []))->pluck('id')->all();

        $vehicle->users()->sync($ids);

        return back()->with('status', 'Affectations mises à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'callsign' => ['nullable', 'string', 'max:50'],
            'registration' => ['nullable', 'string', 'max:50'],
            'center' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(VehicleStatus::class)],
            'commissioned_at' => ['nullable', 'date'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
