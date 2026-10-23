<?php

namespace App\Domain\Webhooks;

use App\Models\VehicleSession;

/**
 * Représentation publique d'une prise / fin de service (API v1 et notifications
 * sortantes). Le véhicule est désigné par son uuid ; jamais d'id interne ni de photo.
 */
class ServicePayload
{
    public static function of(VehicleSession $s): array
    {
        $s->loadMissing(['vehicle:id,uuid,name,callsign', 'user:id,name', 'partner:id,name', 'closer:id,name']);

        return [
            'vehicle' => $s->vehicle ? [
                'uuid' => $s->vehicle->uuid,
                'callsign' => $s->vehicle->callsign,
                'name' => $s->vehicle->name,
            ] : null,
            'source' => $s->source ?? VehicleSession::SOURCE_VULKAIN,
            'external_id' => $s->external_id,
            'close_external_id' => $s->close_external_id,
            'open' => $s->closed_at === null,
            'opened_at' => $s->opened_at?->toIso8601String(),
            'opened_by' => $s->openerName(),
            'partner' => $s->partnerName(),
            'open_mileage' => $s->open_mileage,
            'open_checks' => self::checks($s->open_responses ?? $s->open_steps ?? []),
            'open_notes' => $s->open_notes,
            'closed_at' => $s->closed_at?->toIso8601String(),
            'closed_by' => $s->closerName(),
            'close_reason' => $s->close_reason,
            'close_mileage' => $s->close_mileage,
            'close_checks' => self::checks($s->close_responses ?? []),
            'close_notes' => $s->close_notes,
            'updated_at' => $s->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Contrôles : libellé, référence stable (« ref », champs intra-Chatel), type,
     * valeur, pourcentage, unité, alerte. Jamais la photo ni son chemin.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public static function checks(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($r) => is_array($r) && isset($r['label']))
            ->map(fn (array $r) => [
                'ref' => $r['ref'] ?? null,
                'label' => (string) $r['label'],
                'type' => $r['type'] ?? null,
                'value' => ($r['type'] ?? null) === 'photo' ? null : ($r['value'] ?? null),
                'percent' => $r['percent'] ?? null,
                'unit' => $r['unit'] ?? null,
                'alert' => isset($r['alert']['severity']) ? [
                    'severity' => (string) $r['alert']['severity'],
                    'message' => (string) ($r['alert']['message'] ?? ''),
                ] : null,
            ])->values()->all();
    }
}
