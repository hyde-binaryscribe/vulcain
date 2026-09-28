<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Domain\Fleet\DisinfectionStatus;
use App\Domain\Fleet\DisinfectionType;
use App\Domain\Fleet\MaintenanceStatus;
use App\Models\DisinfectionProtocol;
use App\Models\DisinfectionRecord;
use App\Models\Event;
use App\Models\Location;
use App\Models\Material;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\Sites\SiteScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Application terrain (PWA mobile) pour les salariés : accueil, fiche véhicule
 * simplifiée (checklist, désinfection, entretien), signalement d'anomalie et
 * accès par scan. Réutilise le domaine existant (véhicules, événements,
 * désinfections). Même hôte que l'app (app.vulkain.eu).
 */
class TerrainController extends Controller
{
    public function home(Request $request): Response
    {
        $user = $request->user();

        // « Mes véhicules » = véhicules affectés ; à défaut, tous ceux accessibles.
        $siteIds = SiteScope::forUser($user, session('current_site_id'));
        $assigned = $user->vehicles()->pluck('vehicles.id');

        $vehicles = Vehicle::query()
            ->when($assigned->isNotEmpty(), fn ($q) => $q->whereIn('id', $assigned))
            ->when($assigned->isEmpty() && $siteIds !== null, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->orderBy('name')
            ->get();

        $intervals = VehicleType::query()->whereNotNull('disinfection_interval_days')->pluck('disinfection_interval_days', 'name');
        $openAnomalies = Event::query()
            ->where('type', EventType::ANOMALIE->value)
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->selectRaw('vehicle_id, count(*) as total')
            ->groupBy('vehicle_id')
            ->pluck('total', 'vehicle_id');

        $cards = $vehicles->map(function (Vehicle $v) use ($intervals, $openAnomalies) {
            $lastDisinfection = $v->disinfections()->first()?->performed_at;
            $disinfection = DisinfectionStatus::compute($lastDisinfection, $intervals[$v->type] ?? null);
            $maintenance = MaintenanceStatus::forVehicleRecords(
                $v->maintenances()->get(),
                $v->mileage !== null ? (int) $v->mileage : null,
            );

            return [
                'id' => $v->id,
                'name' => $v->name,
                'callsign' => $v->callsign,
                'type' => $v->type,
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'disinfection_severity' => $disinfection->severity?->value,
                'maintenance_severity' => $maintenance->severity?->value,
                'open_anomalies' => (int) ($openAnomalies[$v->id] ?? 0),
            ];
        });

        return Inertia::render('Terrain/Home', [
            'greeting_name' => $user->name,
            'vehicles' => $cards,
            'totals' => [
                'anomalies' => (int) $openAnomalies->sum(),
                'disinfection_due' => $cards->whereIn('disinfection_severity', ['critical', 'warning'])->count(),
                'maintenance_due' => $cards->whereIn('maintenance_severity', ['critical', 'warning'])->count(),
            ],
            'can_report_anomaly' => $user->can('anomalies.manage'),
        ]);
    }

    public function vehicle(Request $request, Vehicle $vehicle): Response
    {
        $locations = Location::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderBy('display_order')
            ->get();

        $materials = Material::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->with(['items', 'lots'])
            ->orderBy('name')
            ->get();

        $grouped = $locations->map(fn (Location $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'materials' => $materials->where('location_id', $l->id)->map(fn (Material $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'reference' => $m->reference,
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'below_threshold' => $m->minimum_qty > 0 && $m->isBelowThreshold(),
            ])->values(),
        ]);

        $intervalDays = VehicleType::query()->where('name', $vehicle->type)->value('disinfection_interval_days');
        $disinfections = $vehicle->disinfections()->with('user:id,name')->limit(10)->get();
        $disinfectionStatus = DisinfectionStatus::compute($disinfections->first()?->performed_at, $intervalDays);
        $protocols = DisinfectionProtocol::query()
            ->where('is_active', true)
            ->orderBy('display_order')->orderBy('name')
            ->get()
            ->map(fn (DisinfectionProtocol $p) => ['id' => $p->id, 'name' => $p->name, 'type' => $p->type->value, 'steps' => $p->steps()]);

        $maintenanceStatus = MaintenanceStatus::forVehicleRecords(
            $vehicle->maintenances()->get(),
            $vehicle->mileage !== null ? (int) $vehicle->mileage : null,
        );

        $anomalies = Event::query()
            ->where('type', EventType::ANOMALIE->value)
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'priority' => $e->priority,
                'created_at' => $e->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Terrain/Vehicle', [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'callsign' => $vehicle->callsign,
                'type' => $vehicle->type,
                'status' => $vehicle->status->value,
                'status_label' => $vehicle->status->label(),
                'mileage' => $vehicle->mileage,
            ],
            'locations' => $grouped,
            'disinfection' => [
                'interval_days' => $intervalDays,
                'last_at' => $disinfectionStatus->lastAt?->format('d/m/Y H:i'),
                'due_at' => $disinfectionStatus->dueAt?->format('d/m/Y'),
                'severity' => $disinfectionStatus->severity?->value,
                'state_label' => $disinfectionStatus->label(),
                'types' => DisinfectionType::options(),
                'protocols' => $protocols,
                'can_record' => $request->user()->can('disinfections.record'),
                'records' => $disinfections->map(fn (DisinfectionRecord $d) => [
                    'type_label' => $d->type->label(),
                    'performed_at' => $d->performed_at?->format('d/m/Y H:i'),
                    'user' => $d->user?->name,
                ]),
            ],
            'maintenance' => [
                'severity' => $maintenanceStatus->severity?->value,
                'state_label' => $maintenanceStatus->label(),
                'next_due_at' => $maintenanceStatus->dueAt?->format('d/m/Y'),
                'next_due_mileage' => $maintenanceStatus->dueMileage,
                'can_update' => $request->user()->can('vehicles.manage'),
            ],
            'anomalies' => $anomalies,
            'can_report_anomaly' => $request->user()->can('anomalies.manage'),
            'status' => session('status'),
        ]);
    }

    public function anomalyForm(Request $request): Response
    {
        return Inertia::render('Terrain/Anomaly', [
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name', 'callsign']),
            'vehicle_id' => $request->integer('vehicle_id') ?: null,
            'priorities' => Event::PRIORITIES,
            'status' => session('status'),
        ]);
    }

    public function reportAnomaly(Request $request): RedirectResponse
    {
        $orgId = $request->user()->organisation_id;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(Event::PRIORITIES)],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
        ]);

        Event::create([
            ...$validated,
            'type' => EventType::ANOMALIE->value,
            'status' => EventStatus::A_TRAITER->value,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('terrain.home')->with('status', 'Anomalie signalée.');
    }

    public function scan(): Response
    {
        return Inertia::render('Terrain/Scan', [
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name', 'callsign']),
        ]);
    }
}
