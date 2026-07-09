<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VehicleTypeController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        VehicleType::ensureSeeded($this->tenant->organisation());

        // Nombre de véhicules par libellé de type (le véhicule référence son type par nom).
        $usage = Vehicle::query()
            ->selectRaw('type, count(*) as total')
            ->whereNotNull('type')
            ->groupBy('type')
            ->pluck('total', 'type');

        $types = VehicleType::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (VehicleType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'display_order' => $t->display_order,
                'is_active' => $t->is_active,
                'vehicles_count' => (int) ($usage[$t->name] ?? 0),
            ]);

        return Inertia::render('VehicleTypes/Index', [
            'types' => $types,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        VehicleType::create($this->validated($request));

        return back()->with('status', 'Type de véhicule créé.');
    }

    public function update(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $previousName = $vehicleType->name;
        $vehicleType->update($this->validated($request, $vehicleType));

        // Répercuter le renommage sur les véhicules qui portaient l'ancien libellé.
        if ($vehicleType->name !== $previousName) {
            Vehicle::query()->where('type', $previousName)->update(['type' => $vehicleType->name]);
        }

        return back()->with('status', 'Type de véhicule mis à jour.');
    }

    public function toggle(VehicleType $vehicleType): RedirectResponse
    {
        $vehicleType->is_active = ! $vehicleType->is_active;
        $vehicleType->save();

        return back()->with('status', 'Statut du type mis à jour.');
    }

    public function destroy(VehicleType $vehicleType): RedirectResponse
    {
        $vehicleType->delete();

        return back()->with('status', 'Type de véhicule supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?VehicleType $current = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('vehicle_types', 'name')
                    ->where('organisation_id', $this->tenant->id())
                    ->whereNull('deleted_at')
                    ->ignore($current?->id),
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
