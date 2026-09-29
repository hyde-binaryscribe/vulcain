<?php

namespace App\Domain\Fleet;

use App\Models\DisinfectionRecord;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcul centralisé du statut de désinfection d'un véhicule à partir des
 * protocoles qui lui sont affectés (chacun portant sa propre périodicité).
 *
 * Seuls les protocoles datés (frequency_days) génèrent une échéance ; les
 * protocoles « à l'usage » (sans périodicité) sont des procédures à suivre,
 * sans échéance calendaire. Le statut agrégé retient le plus urgent.
 */
final class VehicleDisinfection
{
    /**
     * Statut de désinfection pour un lot de véhicules (requêtes groupées).
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, DisinfectionStatus>  vehicleId => statut
     */
    public static function statusForMany(Collection $vehicles, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $vehicleIds = $vehicles->pluck('id')->all();

        if ($vehicleIds === []) {
            return [];
        }

        // Protocoles datés affectés à chaque véhicule. Restreint aux véhicules du
        // lot (déjà cloisonnés en amont) ; on ne retient que les protocoles actifs
        // et porteurs d'une périodicité.
        $assignments = DB::table('disinfection_protocol_vehicle as pv')
            ->join('disinfection_protocols as p', 'p.id', '=', 'pv.disinfection_protocol_id')
            ->whereIn('pv.vehicle_id', $vehicleIds)
            ->whereNull('p.deleted_at')
            ->where('p.is_active', true)
            ->whereNotNull('p.frequency_days')
            ->where('p.frequency_days', '>', 0)
            ->get(['pv.vehicle_id', 'pv.disinfection_protocol_id as protocol_id', 'p.frequency_days']);

        // Dernière désinfection par (véhicule, protocole).
        $lastRows = DisinfectionRecord::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('disinfection_protocol_id')
            ->selectRaw('vehicle_id, disinfection_protocol_id, max(performed_at) as last_at')
            ->groupBy('vehicle_id', 'disinfection_protocol_id')
            ->get();

        $lastMap = []; // [vehicleId][protocolId] = Carbon
        foreach ($lastRows as $row) {
            $lastMap[$row->vehicle_id][$row->disinfection_protocol_id] =
                $row->last_at !== null ? Carbon::parse($row->last_at) : null;
        }

        $entriesByVehicle = []; // [vehicleId] => list of {interval, last}
        foreach ($assignments as $a) {
            $entriesByVehicle[$a->vehicle_id][] = [
                'interval' => (int) $a->frequency_days,
                'last' => $lastMap[$a->vehicle_id][$a->protocol_id] ?? null,
            ];
        }

        $result = [];
        foreach ($vehicleIds as $id) {
            $result[$id] = DisinfectionStatus::forProtocols($entriesByVehicle[$id] ?? [], $now);
        }

        return $result;
    }

    /** Statut de désinfection d'un seul véhicule. */
    public static function statusFor(Vehicle $vehicle, ?Carbon $now = null): DisinfectionStatus
    {
        return self::statusForMany(collect([$vehicle]), $now)[$vehicle->id]
            ?? DisinfectionStatus::compute(null, null, $now);
    }
}
