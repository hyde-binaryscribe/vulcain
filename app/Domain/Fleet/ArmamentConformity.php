<?php

namespace App\Domain\Fleet;

use App\Models\Location;
use App\Models\Material;
use App\Models\OxygenBottle;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VehicleTypeEquipment;
use App\Models\VehicleTypeOxygen;
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

        // Dotation O2 de référence (répartition en bouteilles) : elle fixe le
        // volume O2 requis du type et remplace alors les lignes « volume » saisies
        // à la main dans l'armement.
        $dotation = $type->oxygenDotation()->with('bottleType')->get();
        $requiredO2 = (int) $dotation->sum(fn (VehicleTypeOxygen $d) => ($d->bottleType?->volume_l ?? 0) * $d->quantity);
        $repartition = $dotation->filter(fn (VehicleTypeOxygen $d) => $d->bottleType !== null)
            ->map(fn (VehicleTypeOxygen $d) => $d->quantity.'× '.$d->bottleType->name)->implode(', ');

        if ($items->isEmpty() && $requiredO2 <= 0) {
            return ['type' => $vehicle->type, 'type_id' => $type->id, 'defined' => false, 'items' => [], 'summary' => null];
        }

        // Stock embarqué, agrégé par type de matériel (lien exact) et par nom
        // normalisé (rapprochement best-effort pour les lignes non reliées).
        $locationIds = Location::query()->where('vehicle_id', $vehicle->id)->pluck('id');
        $stockByName = [];
        $stockByType = [];
        foreach (Material::query()->whereIn('location_id', $locationIds)->with(['items', 'lots'])->get() as $m) {
            $qty = $m->stockQuantity();
            $key = self::normalize($m->name);
            if ($key !== '') {
                $stockByName[$key] = ($stockByName[$key] ?? 0) + $qty;
            }
            if ($m->material_type_id !== null) {
                $stockByType[$m->material_type_id] = ($stockByType[$m->material_type_id] ?? 0) + $qty;
            }
        }

        // Volume d'oxygène total embarqué (somme des bouteilles actives).
        $o2Total = (int) $vehicle->oxygenBottles()->where('is_active', true)->get()
            ->sum(fn (OxygenBottle $b) => $b->gasVolume() ?? 0);

        // Si une dotation O2 structurée existe, elle fait foi : on écarte les
        // lignes « volume » manuelles de l'armement (évite le double comptage).
        $equipmentItems = $requiredO2 > 0
            ? $items->reject(fn (VehicleTypeEquipment $e) => $e->volume_l !== null && $e->volume_l > 0)
            : $items;

        $rows = $equipmentItems->map(function (VehicleTypeEquipment $e) use ($stockByName, $stockByType, $o2Total) {
            $base = [
                'category' => $e->category,
                'designation' => $e->designation,
                'quantity' => $e->quantity,
                'level' => $e->level->value,
                'note' => $e->note,
                'stock' => null,
                'volume_required' => null,
                'volume_onboard' => null,
            ];

            // Ligne à volume (oxygène) : conformité exacte sur le volume total.
            if ($e->volume_l !== null && $e->volume_l > 0) {
                return array_merge($base, [
                    'state' => $o2Total >= $e->volume_l ? 'present' : 'partiel',
                    'volume_required' => $e->volume_l,
                    'volume_onboard' => $o2Total,
                ]);
            }

            // Ligne reliée au catalogue : conformité exacte sur le stock du type.
            if ($e->material_type_id !== null) {
                $stock = (int) ($stockByType[$e->material_type_id] ?? 0);
                $state = $stock >= $e->quantity ? 'present' : ($stock > 0 ? 'partiel' : 'manquant');

                return array_merge($base, ['state' => $state, 'stock' => $stock]);
            }

            // Sinon : rapprochement best-effort par nom.
            $stock = self::matchStock($e->designation, $stockByName);
            $state = $stock === null ? 'inconnu' : ($stock >= $e->quantity ? 'present' : 'partiel');

            return array_merge($base, ['state' => $state, 'stock' => $stock]);
        })->values();

        // Ligne de conformité O2 issue de la dotation (répartition en bouteilles).
        if ($requiredO2 > 0) {
            $rows->push([
                'category' => 'Ventilation / Oxygène',
                'designation' => 'Oxygène — dotation'.($repartition !== '' ? ' ('.$repartition.')' : ''),
                'quantity' => 1,
                'level' => ArmamentLevel::OBLIGATOIRE->value,
                'note' => null,
                'stock' => null,
                'state' => $o2Total >= $requiredO2 ? 'present' : 'partiel',
                'volume_required' => $requiredO2,
                'volume_onboard' => $o2Total,
            ]);
            $rows = $rows->values();
        }

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
                'manquant' => $rows->where('state', 'manquant')->count(),
                'inconnu' => $rows->where('state', 'inconnu')->count(),
                'obligatoire_partiel' => $obligatoire('partiel'),
                'obligatoire_manquant' => $obligatoire('manquant'),
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
