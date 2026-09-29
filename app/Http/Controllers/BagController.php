<?php

namespace App\Http\Controllers;

use App\Domain\Storage\LocationKind;
use App\Models\BagMovement;
use App\Models\Location;
use App\Models\Material;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Espace dédié aux sacs : création, vue d'ensemble et transferts entre véhicules.
 */
class BagController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $bags = Location::query()
            ->where('kind', LocationKind::SAC->value)
            ->with('vehicle:id,name,callsign')
            ->orderBy('name')
            ->get();

        $vehicles = Vehicle::query()->orderBy('name')->get(['id', 'name', 'callsign']);

        $rows = $bags->map(function (Location $bag) {
            $ids = $this->subtreeIds($bag);

            return [
                'id' => $bag->id,
                'name' => $bag->name,
                'vehicle_id' => $bag->vehicle_id,
                'vehicle' => $bag->vehicle?->callsign ?: $bag->vehicle?->name,
                'materials' => Material::query()->whereIn('location_id', $ids)->count(),
                'is_active' => $bag->is_active,
            ];
        });

        $movements = BagMovement::query()
            ->with(['location:id,name', 'fromVehicle:id,name,callsign', 'toVehicle:id,name,callsign', 'user:id,name'])
            ->latest('moved_at')
            ->limit(20)
            ->get()
            ->map(fn (BagMovement $m) => [
                'bag' => $m->location?->name,
                'from' => $m->fromVehicle?->callsign ?: $m->fromVehicle?->name,
                'to' => $m->toVehicle?->callsign ?: $m->toVehicle?->name,
                'user' => $m->user?->name,
                'at' => $m->moved_at?->fr('d/m/Y H:i'),
            ]);

        return Inertia::render('Bags/Index', [
            'bags' => $rows,
            'vehicles' => $vehicles,
            'movements' => $movements,
            'status' => session('status'),
        ]);
    }

    /** Crée un sac (emplacement de nature « Sac »), sur un véhicule ou au dépôt. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'vehicle_id' => [
                'nullable', 'integer',
                Rule::exists('vehicles', 'id')->where('organisation_id', $this->tenant->id())->whereNull('deleted_at'),
            ],
        ]);

        Location::create([
            'kind' => LocationKind::SAC->value,
            'name' => $validated['name'],
            'vehicle_id' => $validated['vehicle_id'] ?? null, // null = dépôt
            'is_active' => true,
        ]);

        return back()->with('status', $validated['vehicle_id'] ?? null ? 'Sac créé.' : 'Sac créé (au dépôt).');
    }

    public function transfer(Request $request, Location $location): RedirectResponse
    {
        abort_unless($location->kind === LocationKind::SAC, 404);

        // Cible : un véhicule de l'organisation, ou le dépôt (null = hors de tout véhicule).
        $validated = $request->validate([
            'to_vehicle_id' => [
                'nullable', 'integer',
                Rule::exists('vehicles', 'id')->where('organisation_id', $location->organisation_id)->whereNull('deleted_at'),
            ],
        ]);

        $to = $validated['to_vehicle_id'] ?? null;

        // Déjà à cet emplacement (même véhicule, ou déjà au dépôt).
        if ($to === $location->vehicle_id) {
            return back()->withErrors([
                'to_vehicle_id' => $to === null ? 'Le sac est déjà au dépôt.' : 'Le sac est déjà sur ce véhicule.',
            ]);
        }

        $from = $location->vehicle_id;
        $ids = $this->subtreeIds($location);

        DB::transaction(function () use ($ids, $to, $location, $from, $request) {
            // Le sac et son contenu (emplacements enfants) suivent la cible.
            Location::query()->whereIn('id', $ids)->update(['vehicle_id' => $to]);

            BagMovement::create([
                'location_id' => $location->id,
                'from_vehicle_id' => $from,
                'to_vehicle_id' => $to,
                'user_id' => $request->user()->id,
                'moved_at' => now(),
            ]);
        });

        return back()->with('status', $to === null ? 'Sac renvoyé au dépôt.' : 'Sac transféré.');
    }

    /**
     * Identifiants du sac et de tous ses emplacements descendants.
     *
     * @return list<int>
     */
    private function subtreeIds(Location $bag): array
    {
        $ids = [$bag->id];
        $frontier = [$bag->id];

        while ($frontier !== []) {
            $children = Location::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));
            if ($children === []) {
                break;
            }
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }
}
