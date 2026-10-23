<?php

namespace App\Domain\Fleet;

use App\Models\Location;
use App\Models\Material;
use App\Models\OxygenBottle;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VehicleTypeEquipment;
use Illuminate\Support\Str;

/**
 * Conformité d'un véhicule à l'armement de référence de son type : l'oxygène est
 * vérifié exactement (somme des volumes des bouteilles vs volume requis), le
 * reste par rapprochement de nom best-effort (présent / partiel / à vérifier).
 */
class ArmamentConformity
{
    /**
     * @return array<string, mixed>|null  null si aucun type exploitable
     */
    public static function for(Vehicle $vehicle): ?array
    {
        if ($vehicle->type === null || $vehicle->type === '') {
            return null;
        }

        $type = VehicleType::query()->where('name', $vehicle->type)->first();
        if ($type === null) {
            return null;
        }

        $items = $type->equipment()->orderBy('display_order')->orderBy('id')->get();
        if ($items->isEmpty()) {
            return ['type' => $vehicle->type, 'type_id' => $type->id, 'defined' => false, 'items' => [], 'summary' => null];
        }

        // Stock embarqué agrégé par nom normalisé (pour le rapprochement matériel).
        $locationIds = Location::query()->where('vehicle_id', $vehicle->id)->pluck('id');
        $stockByName = [];
        foreach (Material::query()->whereIn('location_id', $locationIds)->with(['items', 'lots'])->get() as $m) {
            $key = self::normalize($m->name);
            if ($key !== '') {
                $stockByName[$key] = ($stockByName[$key] ?? 0) + $m->stockQuantity();
            }
        }

        // Volume d'oxygène total embarqué (somme des bouteilles actives).
        $o2Total = (int) $vehicle->oxygenBottles()->where('is_active', true)->get()
            ->sum(fn (OxygenBottle $b) => $b->gasVolume() ?? 0);

        $rows = $items->map(function (VehicleTypeEquipment $e) use ($stockByName, $o2Total) {
            if ($e->volume_l !== null && $e->volume_l > 0) {
                return [
                    'category' => $e->category,
                    'designation' => $e->designation,
                    'quantity' => $e->quantity,
                    'level' => $e->level->value,
                    'note' => $e->note,
                    'state' => $o2Total >= $e->volume_l ? 'present' : 'partiel',
                    'stock' => null,
                    'volume_required' => $e->volume_l,
                    'volume_onboard' => $o2Total,
                ];
            }

            $stock = self::matchStock($e->designation, $stockByName);
            $state = $stock === null ? 'inconnu' : ($stock >= $e->quantity ? 'present' : 'partiel');

            return [
                'category' => $e->category,
                'designation' => $e->designation,
                'quantity' => $e->quantity,
                'level' => $e->level->value,
                'note' => $e->note,
                'state' => $state,
                'stock' => $stock,
                'volume_required' => null,
                'volume_onboard' => null,
            ];
        })->values();

        $obligatoire = fn ($state) => $rows->filter(fn ($r) => $r['level'] === ArmamentLevel::OBLIGATOIRE->value && $r['state'] === $state)->count();

        return [
            'type' => $vehicle->type,
            'type_id' => $type->id,
            'defined' => true,
            'items' => $rows,
            'summary' => [
                'total' => $rows->count(),
                'obligatoire' => $rows->where('level', ArmamentLevel::OBLIGATOIRE->value)->count(),
                'present' => $rows->where('state', 'present')->count(),
                'partiel' => $rows->where('state', 'partiel')->count(),
                'inconnu' => $rows->where('state', 'inconnu')->count(),
                'obligatoire_partiel' => $obligatoire('partiel'),
                'obligatoire_inconnu' => $obligatoire('inconnu'),
            ],
        ];
    }

    /** Normalise un libellé pour le rapprochement (sans accents, minuscules, alphanum). */
    private static function normalize(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($s))));
    }

    /**
     * @param  array<string, int>  $stockByName
     */
    private static function matchStock(string $designation, array $stockByName): ?int
    {
        $needle = self::normalize($designation);
        if ($needle === '') {
            return null;
        }
        foreach ($stockByName as $name => $stock) {
            if (str_contains($name, $needle) || str_contains($needle, $name)) {
                return $stock;
            }
        }

        return null;
    }
}
