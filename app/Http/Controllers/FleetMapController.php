<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehiclePosition;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suivi flotte : carte de la dernière position connue de chaque véhicule
 * équipé d'un boîtier télématique (données reçues via le webhook Traccar).
 */
class FleetMapController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $vehicles = Vehicle::query()
            ->whereNotNull('telematics_imei')
            ->orderBy('name')
            ->get(['id', 'name', 'callsign', 'type', 'status']);

        $ids = $vehicles->pluck('id');

        // Dernière position par véhicule (MAX(id) = insertion la plus récente).
        $last = $ids->isEmpty()
            ? collect()
            : VehiclePosition::query()
                ->whereIn('id', VehiclePosition::query()
                    ->selectRaw('MAX(id) as id')
                    ->whereIn('vehicle_id', $ids)
                    ->groupBy('vehicle_id')
                    ->pluck('id'))
                ->get()
                ->keyBy('vehicle_id');

        $rows = $vehicles->map(function (Vehicle $v) use ($last) {
            $p = $last->get($v->id);
            $attrs = $p?->attrs ?? [];

            return [
                'id' => $v->id,
                'name' => $v->callsign ?: $v->name,
                'type' => $v->type,
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'position' => $p === null ? null : [
                    'lat' => $p->latitude,
                    'lon' => $p->longitude,
                    'speed' => $p->speed,
                    'device_time' => $p->device_time?->format('d/m/Y H:i'),
                    'ago' => $p->device_time?->diffForHumans(),
                    'fault_count' => (int) ($attrs['faultCount'] ?? 0),
                    'dtcs' => trim((string) ($attrs['dtcs'] ?? '')) ?: null,
                ],
            ];
        })->values();

        return Inertia::render('Fleet/Map', [
            'vehicles' => $rows,
            'located' => $rows->whereNotNull('position')->count(),
        ]);
    }
}
