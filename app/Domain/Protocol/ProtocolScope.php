<?php

namespace App\Domain\Protocol;

use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\ProtocolTemplate;
use App\Models\StockLot;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Résout le périmètre d'un modèle de protocole : quels emplacements et quels
 * matériels sont concernés. Le contenu d'un protocole n'est plus curé à la
 * main — il est déduit de la cible (véhicule ou emplacement + enfants), moins
 * les exclusions.
 */
class ProtocolScope
{
    /**
     * Emplacements du périmètre.
     *
     * @return list<int>
     */
    public function locationIds(ProtocolTemplate $template): array
    {
        if ($template->scope_type === ProtocolScopeType::VEHICLE) {
            if ($template->scope_id === null) {
                return [];
            }

            return Location::query()
                ->where('vehicle_id', $template->scope_id)
                ->pluck('id')
                ->all();
        }

        $base = $template->scope_id !== null ? Location::query()->find($template->scope_id) : null;
        if ($base === null) {
            return [];
        }

        return $template->include_children ? $base->descendantAndSelfIds() : [$base->id];
    }

    /**
     * Matériels du périmètre (hors exclusions), triés par emplacement puis nom.
     *
     * Un matériel est « présent » dans le périmètre s'il y possède un exemplaire
     * (n° de série), un lot (consommable), ou s'il y est lui-même rattaché
     * (mode quantité, ou exemplaires/lots héritant de l'emplacement du modèle).
     * Les exemplaires / lots retournés sont filtrés à ceux effectivement présents.
     *
     * @return Collection<int, Material>
     */
    public function materials(ProtocolTemplate $template): Collection
    {
        $locationIds = $this->locationIds($template);
        if ($locationIds === []) {
            return collect();
        }

        $excluded = $template->excluded_material_ids ?? [];

        $locationLoad = [
            'location:id,name,kind,parent_id,vehicle_id',
            'location.vehicle:id,name',
            'location.parent:id,name,parent_id,vehicle_id',
        ];

        $serialIds = MaterialItem::query()->whereIn('location_id', $locationIds)->pluck('material_id');
        $lotIds = StockLot::query()->whereIn('location_id', $locationIds)->pluck('material_id');
        $directIds = Material::query()->whereIn('location_id', $locationIds)->pluck('id');

        $ids = $serialIds->merge($lotIds)->merge($directIds)->unique()->diff($excluded)->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $materials = Material::query()
            ->whereIn('id', $ids)
            ->with([
                ...$locationLoad,
                // Exemplaires série (une ligne de contrôle par n° de série) et lots
                // consommables (péremption la plus proche connue).
                'items' => fn ($q) => $q->with($locationLoad)->orderBy('serial_number'),
                'lots' => fn ($q) => $q->with($locationLoad)->orderByRaw('expiry_date is null')->orderBy('expiry_date'),
            ])
            ->orderBy('location_id')
            ->orderBy('name')
            ->get();

        // On ne conserve que les exemplaires / lots effectivement dans le périmètre
        // (emplacement propre, ou à défaut celui du modèle).
        foreach ($materials as $material) {
            $material->setRelation('items', $material->items
                ->filter(fn (MaterialItem $it) => in_array($it->location_id ?? $material->location_id, $locationIds, true))
                ->values());
            $material->setRelation('lots', $material->lots
                ->filter(fn (StockLot $l) => in_array($l->location_id ?? $material->location_id, $locationIds, true))
                ->values());
        }

        return $materials;
    }

    /** Véhicule déduit du périmètre (null pour un emplacement fixe / dépôt). */
    public function vehicle(ProtocolTemplate $template): ?Vehicle
    {
        if ($template->scope_type === ProtocolScopeType::VEHICLE) {
            return $template->scope_id !== null ? Vehicle::query()->find($template->scope_id) : null;
        }

        $base = $template->scope_id !== null ? Location::query()->find($template->scope_id) : null;

        return $base?->vehicle;
    }

    /** Libellé lisible de la cible (nom du véhicule ou chemin de l'emplacement). */
    public function targetLabel(ProtocolTemplate $template): string
    {
        if ($template->scope_type === ProtocolScopeType::VEHICLE) {
            return $this->vehicle($template)?->name ?? 'Véhicule';
        }

        $base = $template->scope_id !== null ? Location::query()->find($template->scope_id) : null;

        return $base?->fullPath() ?? 'Emplacement';
    }
}
