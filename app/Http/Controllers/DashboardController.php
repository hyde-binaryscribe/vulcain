<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Domain\Fleet\DisinfectionStatus;
use App\Domain\Fleet\MaintenanceStatus;
use App\Domain\Fleet\VehicleStatus;
use App\Domain\Support\Severity;
use App\Models\DisinfectionRecord;
use App\Models\Event;
use App\Models\MaintenanceRecord;
use App\Models\Material;
use App\Models\Protocol;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::today();
        $soon = $today->copy()->addDays(30);

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

        return Inertia::render('Dashboard', [
            'stats' => [
                'vehicles' => Vehicle::query()->count(),
                'vehicles_available' => Vehicle::query()->where('status', VehicleStatus::DISPONIBLE->value)->count(),
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
            ],
        ]);
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
     * prévoir prochainement (orange), selon la périodicité imposée par le type.
     *
     * @return array{overdue:int,soon:int}
     */
    private function disinfectionCounts(): array
    {
        $intervals = VehicleType::query()
            ->whereNotNull('disinfection_interval_days')
            ->pluck('disinfection_interval_days', 'name'); // name => days

        if ($intervals->isEmpty()) {
            return ['overdue' => 0, 'soon' => 0];
        }

        $vehicles = Vehicle::query()
            ->whereIn('type', $intervals->keys())
            ->get(['id', 'type']);

        $lastByVehicle = DisinfectionRecord::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->selectRaw('vehicle_id, max(performed_at) as last_at')
            ->groupBy('vehicle_id')
            ->pluck('last_at', 'vehicle_id');

        $overdue = 0;
        $soon = 0;

        foreach ($vehicles as $vehicle) {
            $last = $lastByVehicle[$vehicle->id] ?? null;
            $status = DisinfectionStatus::compute(
                $last !== null ? Carbon::parse($last) : null,
                (int) $intervals[$vehicle->type],
            );

            if ($status->severity === Severity::CRITICAL) {
                $overdue++;
            } elseif ($status->severity === Severity::WARNING) {
                $soon++;
            }
        }

        return ['overdue' => $overdue, 'soon' => $soon];
    }
}
