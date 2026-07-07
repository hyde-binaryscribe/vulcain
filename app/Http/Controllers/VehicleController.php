<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
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
