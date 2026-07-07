<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $locations = Location::query()
            ->with(['vehicle:id,name', 'parent:id,name'])
            ->orderByRaw('vehicle_id is null')
            ->orderBy('vehicle_id')
            ->orderBy('display_order')
            ->get()
            ->map(fn (Location $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'vehicle' => $l->vehicle?->name,
                'vehicle_id' => $l->vehicle_id,
                'parent' => $l->parent?->name,
                'parent_id' => $l->parent_id,
                'display_order' => $l->display_order,
                'is_active' => $l->is_active,
            ]);

        return Inertia::render('Locations/Index', [
            'locations' => $locations,
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name']),
            'parents' => Location::query()->orderBy('name')->get(['id', 'name']),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Location::create($this->validated($request));

        return back()->with('status', 'Emplacement créé.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->validated($request, $location));

        return back()->with('status', 'Emplacement mis à jour.');
    }

    public function toggle(Location $location): RedirectResponse
    {
        $location->is_active = ! $location->is_active;
        $location->save();

        return back()->with('status', 'Statut de l’emplacement mis à jour.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return back()->with('status', 'Emplacement supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Location $current = null): array
    {
        $orgId = $this->tenant->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'vehicle_id' => [
                'nullable',
                Rule::exists('vehicles', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at'),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at'),
                Rule::notIn([$current?->id]), // un emplacement ne peut être son propre parent
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
