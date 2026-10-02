<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Domain\Fleet\MaintenanceStatus;
use App\Domain\Fleet\VehicleDisinfection;
use App\Domain\Fleet\VehicleStatus;
use App\Domain\Support\Severity;
use App\Models\Document;
use App\Models\Event;
use App\Models\Location;
use App\Models\MaintenanceRecord;
use App\Models\Material;
use App\Models\Protocol;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $today = Carbon::today();
        $soon = $today->copy()->addDays($this->tenant->organisation()->expiryAlertDays());

        $lowStock = Material::query()
            ->where('minimum_qty', '>', 0)
            ->with(['lots:id,material_id,quantity', 'items:id,material_id'])
            ->get()
            ->filter(fn (Material $m) => $m->isBelowThreshold())
            ->count();

        $openEvents = Event::query()
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->count();

        $disinfection = $this->disinfectionCounts();
        $maintenance = $this->maintenanceCounts();

        $availability = $this->availability();

        $fleet = $this->fleet();

        return Inertia::render('Dashboard', [
            'stats' => [
                'vehicles' => $availability['total'],
                'vehicles_available' => $availability['available'],
                'vehicles_in_service' => $availability['in_service'],
                'vehicles_unavailable' => $availability['unavailable'],
                'users' => User::query()->where('is_active', true)->count(),
                'protocols_draft' => Protocol::query()->where('status', Protocol::STATUS_DRAFT)->count(),
                'protocols_total' => Protocol::query()->count(),
            ],
            'alerts' => [
                'expired' => StockLot::query()->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today)->count(),
                'expiring_soon' => StockLot::query()->whereNotNull('expiry_date')->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', $soon)->count(),
                'low_stock' => $lowStock,
                'open_events' => $openEvents,
                'disinfection_overdue' => $disinfection['overdue'],
                'disinfection_soon' => $disinfection['soon'],
                'maintenance_overdue' => $maintenance['overdue'],
                'maintenance_soon' => $maintenance['soon'],
                'documents_expired' => Document::query()->whereNotNull('expires_at')->whereDate('expires_at', '<', $today)->count(),
                'documents_soon' => Document::query()->whereNotNull('expires_at')->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $soon)->count(),
            ],
            // Vue parc : une ligne par véhicule, retards en tête.
            'fleet' => $fleet,
        ]);
    }

    /**
     * État du parc véhicule par véhicule : statut désinfection, entretien,
     * événements ouverts et documents, avec la pire gravité pour le tri.
     *
     * @return list<array<string, mixed>>
     */
    private function fleet(): array
    {
        $today = Carbon::today();
        $soonLimit = $today->copy()->addDays($this->tenant->organisation()->expiryAlertDays());

        $vehicles = Vehicle::query()->orderBy('name')->get(['id', 'name', 'callsign', 'type', 'status', 'mileage']);
        if ($vehicles->isEmpty()) {
            return [];
        }

        $ids = $vehicles->pluck('id');
        $now = Carbon::now();

        $disinfection = VehicleDisinfection::statusForMany($vehicles, $now);

        // Consommables : manquements par véhicule (matériel sous le niveau théorique).
        $consumables = $this->consumablesShortageByVehicle($ids);

        $maintenanceRecords = MaintenanceRecord::query()
            ->whereIn('vehicle_id', $ids)
            ->where(fn ($q) => $q->whereNotNull('next_due_at')->orWhereNotNull('next_due_mileage'))
            ->get(['id', 'vehicle_id', 'type', 'performed_at', 'next_due_at', 'next_due_mileage'])
            ->groupBy('vehicle_id');

        $openEvents = Event::query()
            ->whereIn('vehicle_id', $ids)
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->selectRaw('vehicle_id, count(*) as total')
            ->groupBy('vehicle_id')
            ->pluck('total', 'vehicle_id');

        // Documents véhicule : pire gravité par véhicule (expiré > bientôt).
        $docSeverity = [];
        Document::query()
            ->where('documentable_type', Vehicle::class)
            ->whereIn('documentable_id', $ids)
            ->whereNotNull('expires_at')
            ->get(['documentable_id', 'expires_at'])
            ->each(function (Document $d) use (&$docSeverity, $today, $soonLimit) {
                $sev = $d->expires_at->lessThan($today) ? Severity::CRITICAL
                    : ($d->expires_at->lessThanOrEqualTo($soonLimit) ? Severity::WARNING : null);
                if ($sev === null) {
                    return;
                }
                $current = $docSeverity[$d->documentable_id] ?? null;
                if ($current === null || $sev->rank() > $current->rank()) {
                    $docSeverity[$d->documentable_id] = $sev;
                }
            });

        $alertDays = $this->tenant->organisation()->maintenanceAlertDays();
        $alertKm = $this->tenant->organisation()->maintenanceAlertKm();

        $rows = $vehicles->map(function (Vehicle $v) use ($disinfection, $consumables, $maintenanceRecords, $openEvents, $docSeverity, $now, $alertDays, $alertKm) {
            $disinfSev = $disinfection[$v->id]?->severity ?? null;
            $maint = MaintenanceStatus::forVehicleRecords(
                $maintenanceRecords[$v->id] ?? collect(),
                $v->mileage !== null ? (int) $v->mileage : null,
                $now,
                $alertDays,
                $alertKm,
            );
            $docSev = $docSeverity[$v->id] ?? null;
            $events = (int) ($openEvents[$v->id] ?? 0);

            $conso = $consumables[$v->id] ?? ['missing' => 0, 'severity' => null];
            $consoSev = $conso['severity'];

            $worst = collect([$disinfSev, $maint->severity, $docSev, $consoSev])
                ->filter()
                ->map(fn (Severity $s) => $s->rank())
                ->max() ?? 0;

            return [
                'id' => $v->id,
                'name' => $v->name,
                'callsign' => $v->callsign,
                'type' => $v->type,
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'disinfection' => $disinfSev?->value,
                'maintenance' => $maint->severity?->value,
                'documents' => $docSev?->value,
                'consumables' => $consoSev?->value,
                'consumables_missing' => $conso['missing'],
                'open_events' => $events,
                'worst' => $worst,
            ];
        });

        // Retards / alertes en tête, puis par nom.
        return $rows->sortByDesc('worst')->values()->all();
    }

    /**
     * Disponibilité du parc pour le donut : disponible (prêt à partir),
     * en service (session ouverte) et indisponible (le reste). Les trois
     * totalisent le parc.
     *
     * @return array{total:int,available:int,in_service:int,unavailable:int}
     */
    private function availability(): array
    {
        $total = Vehicle::query()->count();

        $inServiceIds = VehicleSession::query()->open()->pluck('vehicle_id')->unique();
        $inService = $inServiceIds->count();

        $available = Vehicle::query()
            ->where('status', VehicleStatus::DISPONIBLE->value)
            ->when($inServiceIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $inServiceIds))
            ->count();

        return [
            'total' => $total,
            'available' => $available,
            'in_service' => $inService,
            'unavailable' => max(0, $total - $available - $inService),
        ];
    }

    /**
     * Manquements de consommables par véhicule : nombre de références sous le
     * niveau théorique, avec la gravité (critique si une référence passe aussi
     * sous son seuil mini, sinon « à prévoir »).
     *
     * @param  \Illuminate\Support\Collection<int,int>  $vehicleIds
     * @return array<int,array{missing:int,severity:?Severity}>
     */
    private function consumablesShortageByVehicle($vehicleIds): array
    {
        if ($vehicleIds->isEmpty()) {
            return [];
        }

        $locationVehicle = Location::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->pluck('vehicle_id', 'id');

        if ($locationVehicle->isEmpty()) {
            return [];
        }

        $materials = Material::query()
            ->whereIn('location_id', $locationVehicle->keys())
            ->with(['items:id,material_id', 'lots:id,material_id,quantity'])
            ->get(['id', 'location_id', 'tracking_mode', 'theoretical_qty', 'minimum_qty', 'current_qty']);

        $byVehicle = [];
        foreach ($materials as $m) {
            $vehicleId = $locationVehicle[$m->location_id] ?? null;
            if ($vehicleId === null) {
                continue;
            }

            $stock = match ($m->tracking_mode) {
                Material::MODE_SERIAL => $m->items->count(),
                Material::MODE_LOT => (int) $m->lots->sum('quantity'),
                default => (int) $m->current_qty,
            };
            $missing = max(0, (int) $m->theoretical_qty - $stock);
            if ($missing <= 0) {
                continue;
            }

            $current = $byVehicle[$vehicleId] ?? ['missing' => 0, 'severity' => Severity::WARNING];
            $current['missing']++;
            // Sous le seuil mini : manquement critique pour ce véhicule.
            if ($m->minimum_qty > 0 && $stock < $m->minimum_qty) {
                $current['severity'] = Severity::CRITICAL;
            }
            $byVehicle[$vehicleId] = $current;
        }

        return $byVehicle;
    }

    /**
     * Compte les véhicules dont l'entretien est en retard (rouge) ou à prévoir
     * (orange), selon l'échéance suivante (date et/ou km) des opérations.
     *
     * @return array{overdue:int,soon:int}
     */
    private function maintenanceCounts(): array
    {
        $records = MaintenanceRecord::query()
            ->where(fn ($q) => $q->whereNotNull('next_due_at')->orWhereNotNull('next_due_mileage'))
            ->get(['id', 'vehicle_id', 'type', 'performed_at', 'next_due_at', 'next_due_mileage']);

        if ($records->isEmpty()) {
            return ['overdue' => 0, 'soon' => 0];
        }

        $mileageByVehicle = Vehicle::query()
            ->whereIn('id', $records->pluck('vehicle_id')->unique())
            ->pluck('mileage', 'id');

        $overdue = 0;
        $soon = 0;

        foreach ($records->groupBy('vehicle_id') as $vehicleId => $vehicleRecords) {
            $mileage = $mileageByVehicle[$vehicleId] ?? null;
            $status = MaintenanceStatus::forVehicleRecords(
                $vehicleRecords,
                $mileage !== null ? (int) $mileage : null,
                null,
                $this->tenant->organisation()->maintenanceAlertDays(),
                $this->tenant->organisation()->maintenanceAlertKm(),
            );

            if ($status->severity === Severity::CRITICAL) {
                $overdue++;
            } elseif ($status->severity === Severity::WARNING) {
                $soon++;
            }
        }

        return ['overdue' => $overdue, 'soon' => $soon];
    }

    /**
     * Compte les véhicules dont la désinfection est en retard (rouge) ou à
     * prévoir prochainement (orange), selon les protocoles affectés à chaque
     * véhicule (chacun portant sa propre périodicité).
     *
     * @return array{overdue:int,soon:int}
     */
    private function disinfectionCounts(): array
    {
        $vehicles = Vehicle::query()->get(['id']);

        if ($vehicles->isEmpty()) {
            return ['overdue' => 0, 'soon' => 0];
        }

        $statuses = VehicleDisinfection::statusForMany($vehicles);

        $overdue = 0;
        $soon = 0;

        foreach ($statuses as $status) {
            if ($status->severity === Severity::CRITICAL) {
                $overdue++;
            } elseif ($status->severity === Severity::WARNING) {
                $soon++;
            }
        }

        return ['overdue' => $overdue, 'soon' => $soon];
    }
}
