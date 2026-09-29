<?php

namespace App\Domain\Fleet;

use App\Models\FuelRecord;
use Illuminate\Support\Collection;

/**
 * Calcule la consommation (L/100 km) à partir des pleins d'un véhicule.
 *
 * Méthode « plein à plein » : entre deux pleins complets, la consommation est
 * le carburant ajouté (y compris pleins partiels intermédiaires) divisé par la
 * distance parcourue. Le premier plein complet sert de référence (pas de conso).
 */
class FuelConsumption
{
    /**
     * @param  Collection<int, FuelRecord>  $records
     * @return array{records:list<array<string,mixed>>, average:?float, last:?float, total_liters:float, total_cost:?float, count:int}
     */
    public static function summary(Collection $records): array
    {
        // Ordre chronologique par kilométrage (départage par date).
        $asc = $records->sortBy([['mileage', 'asc'], ['filled_at', 'asc']])->values();

        $consById = [];
        $lastFullMileage = null;
        $acc = 0.0;
        $totalDist = 0;
        $totalIntervalLiters = 0.0;

        foreach ($asc as $rec) {
            $acc += (float) $rec->liters;

            if ($rec->full_tank) {
                if ($lastFullMileage !== null && $rec->mileage > $lastFullMileage) {
                    $dist = $rec->mileage - $lastFullMileage;
                    $consById[$rec->id] = round($acc / $dist * 100, 1);
                    $totalDist += $dist;
                    $totalIntervalLiters += $acc;
                }
                $lastFullMileage = $rec->mileage;
                $acc = 0.0;
            }
        }

        // Affichage : du plus récent au plus ancien.
        $rows = $records->sortByDesc([['mileage', 'desc'], ['filled_at', 'desc']])->values()
            ->map(fn (FuelRecord $r) => [
                'id' => $r->id,
                'filled_at' => $r->filled_at?->format('d/m/Y'),
                'mileage' => $r->mileage,
                'liters' => (float) $r->liters,
                'price_per_liter' => $r->price_per_liter !== null ? (float) $r->price_per_liter : null,
                'cost' => $r->cost !== null ? (float) $r->cost : null,
                'full_tank' => $r->full_tank,
                'consumption' => $consById[$r->id] ?? null,
                'user' => $r->user?->name,
            ])->all();

        // « Dernière conso » = celle du plein le plus récent qui en a une.
        $last = null;
        foreach ($rows as $row) {
            if ($row['consumption'] !== null) {
                $last = $row['consumption'];
                break;
            }
        }

        return [
            'records' => $rows,
            'average' => $totalDist > 0 ? round($totalIntervalLiters / $totalDist * 100, 1) : null,
            'last' => $last,
            'total_liters' => round((float) $records->sum(fn (FuelRecord $r) => (float) $r->liters), 2),
            'total_cost' => $records->whereNotNull('cost')->isNotEmpty()
                ? round((float) $records->sum(fn (FuelRecord $r) => (float) $r->cost), 2)
                : null,
            'count' => $records->count(),
        ];
    }
}
