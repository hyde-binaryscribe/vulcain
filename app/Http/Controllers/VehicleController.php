<?php

namespace App\Http\Controllers;

use App\Domain\Billing\PlanLimits;
use App\Domain\Fleet\DisinfectionStatus;
use App\Domain\Fleet\DisinfectionType;
use App\Domain\Fleet\MaintenanceStatus;
use App\Domain\Fleet\MaintenanceType;
use App\Domain\Fleet\VehicleStatus;
use App\Models\ActivityLog;
use App\Models\DisinfectionProtocol;
use App\Models\DisinfectionRecord;
use App\Models\Location;
use App\Models\MaintenanceRecord;
use App\Models\Material;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use App\Support\Sites\SiteScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function show(Vehicle $vehicle): Response
    {
        $vehicle->load('users:id,name');

        $locations = Location::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderBy('display_order')
            ->get();

        $materials = Material::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->with(['category:id,name', 'type:id,name', 'items', 'lots'])
            ->orderBy('name')
            ->get()
            ->map(fn (Material $m) => $this->materialSummary($m));

        $grouped = $locations->map(fn (Location $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'materials' => $materials->where('location_id', $l->id)->values(),
        ]);

        // Traçabilité des désinfections : périodicité (via le type), historique et statut.
        $intervalDays = VehicleType::query()->where('name', $vehicle->type)->value('disinfection_interval_days');
        $disinfections = $vehicle->disinfections()->with(['user:id,name', 'protocol:id,name'])->limit(50)->get();
        $disinfectionStatus = DisinfectionStatus::compute($disinfections->first()?->performed_at, $intervalDays);
        $disinfectionProtocols = DisinfectionProtocol::query()
            ->where('is_active', true)
            ->orderBy('display_order')->orderBy('name')
            ->get()
            ->map(fn (DisinfectionProtocol $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type->value,
                'steps' => $p->steps(),
            ]);

        // Suivi mécanique : historique, échéance et statut agrégé.
        $maintenances = $vehicle->maintenances()->with('user:id,name')->limit(50)->get();
        $maintenanceStatus = MaintenanceStatus::forVehicleRecords($maintenances, $vehicle->mileage !== null ? (int) $vehicle->mileage : null);

        return Inertia::render('Vehicles/Show', [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'type' => $vehicle->type,
                'callsign' => $vehicle->callsign,
                'registration' => $vehicle->registration,
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
            'disinfection' => [
                'interval_days' => $intervalDays,
                'last_at' => $disinfectionStatus->lastAt?->format('d/m/Y H:i'),
                'due_at' => $disinfectionStatus->dueAt?->format('d/m/Y'),
                'state' => $disinfectionStatus->state,
                'state_label' => $disinfectionStatus->label(),
                'severity' => $disinfectionStatus->severity?->value,
                'types' => DisinfectionType::options(),
                'protocols' => $disinfectionProtocols,
                'can_record' => auth()->user()?->can('disinfections.record') ?? false,
                'records' => $disinfections->map(fn (DisinfectionRecord $d) => [
                    'id' => $d->id,
                    'type_label' => $d->type->label(),
                    'protocol' => $d->protocol?->name,
                    'steps' => $d->stepProgress(),
                    'performed_at' => $d->performed_at?->format('d/m/Y H:i'),
                    'user' => $d->user?->name,
                    'notes' => $d->notes,
                ]),
            ],
            'maintenance' => [
                'state' => $maintenanceStatus->state,
                'state_label' => $maintenanceStatus->label(),
                'severity' => $maintenanceStatus->severity?->value,
                'next_due_at' => $maintenanceStatus->dueAt?->format('d/m/Y'),
                'next_due_mileage' => $maintenanceStatus->dueMileage,
                'types' => MaintenanceType::options(),
                'records' => $maintenances->map(fn (MaintenanceRecord $m) => [
                    'id' => $m->id,
                    'type_label' => $m->type->label(),
                    'performed_at' => $m->performed_at?->format('d/m/Y'),
                    'mileage' => $m->mileage,
                    'cost' => $m->cost,
                    'provider' => $m->provider,
                    'notes' => $m->notes,
                    'next_due_at' => $m->next_due_at?->format('d/m/Y'),
                    'next_due_mileage' => $m->next_due_mileage,
                    'user' => $m->user?->name,
                ]),
            ],
            'history' => ActivityLog::query()
                ->forSubjects([
                    Vehicle::class => [$vehicle->id],
                    Location::class => $locations->pluck('id')->all(),
                    Material::class => $materials->pluck('id')->all(),
                ])
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (ActivityLog $l) => ActivityController::format($l)),
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
            'brand' => $m->brand,
            'reference' => $m->reference,
            'type' => $m->type?->name,
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

    public function index(Request $request): Response
    {
        VehicleType::ensureSeeded($this->tenant->organisation());

        $siteIds = SiteScope::forUser($request->user(), session('current_site_id'));

        $vehicles = Vehicle::query()
            ->with(['users:id,name', 'site:id,name'])
            ->when($siteIds !== null, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->orderBy('name')
            ->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'callsign' => $v->callsign,
                'registration' => $v->registration,
                'site' => $v->site?->name,
                'site_id' => $v->site_id,
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
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => VehicleStatus::options(),
            'vehicleTypes' => VehicleType::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->pluck('name'),
            // Modèles disponibles : à la création, leurs emplacements sont générés.
            'vehicleModels' => VehicleModel::query()
                ->where('is_active', true)
                ->withCount('templateLocations')
                ->orderBy('display_order')
                ->orderBy('name')
                ->get()
                ->map(fn (VehicleModel $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'emplacements_count' => $m->template_locations_count,
                ]),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, PlanLimits $limits): RedirectResponse
    {
        if ($message = $limits->check('vehicles', Vehicle::query()->count())) {
            return back()->with('error', $message);
        }

        $vehicle = Vehicle::create($this->validated($request));

        // Génération automatique des emplacements depuis le modèle choisi.
        if ($vehicle->vehicle_model_id !== null) {
            $model = VehicleModel::query()->find($vehicle->vehicle_model_id);
            $model?->generateLocationsFor($vehicle);
        }

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
            'site_id' => ['nullable', Rule::exists('sites', 'id')->where('organisation_id', $this->tenant->id())->whereNull('deleted_at')],
            'type' => ['nullable', 'string', 'max:50'],
            'vehicle_model_id' => ['nullable', Rule::exists('vehicle_models', 'id')->where('organisation_id', $this->tenant->id())->whereNull('deleted_at')],
            'callsign' => ['nullable', 'string', 'max:50'],
            'registration' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::enum(VehicleStatus::class)],
            'commissioned_at' => ['nullable', 'date'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
