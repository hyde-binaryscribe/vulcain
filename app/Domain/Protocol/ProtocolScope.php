<?php

namespace App\Domain\Protocol;

use App\Models\Location;
use App\Models\Material;
use App\Models\ProtocolTemplate;
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
     * @return Collection<int, Material>
     */
    public function materials(ProtocolTemplate $template): Collection
    {
        $locationIds = $this->locationIds($template);
        if ($locationIds === []) {
            return collect();
        }

        $excluded = $template->excluded_material_ids ?? [];

        return Material::query()
            ->whereIn('location_id', $locationIds)
            ->when($excluded !== [], fn ($q) => $q->whereNotIn('id', $excluded))
            ->with([
                'location:id,name,parent_id,vehicle_id',
                'location.vehicle:id,name',
                'location.parent:id,name,parent_id,vehicle_id',
            ])
            ->orderBy('location_id')
            ->orderBy('name')
            ->get();
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
