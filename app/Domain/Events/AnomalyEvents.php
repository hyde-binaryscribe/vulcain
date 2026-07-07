<?php

namespace App\Domain\Events;

use App\Models\Event;
use App\Models\Protocol;
use App\Models\ProtocolItem;
use App\Models\User;

/**
 * Génère des événements de gestion à partir des anomalies constatées lors de
 * la validation d'un protocole (une anomalie non déjà rattachée → un événement).
 */
class AnomalyEvents
{
    /** @return int Nombre d'événements créés. */
    public function fromProtocol(Protocol $protocol, User $author): int
    {
        $created = 0;

        foreach ($protocol->items as $item) {
            if (! $item->isAnomaly()) {
                continue;
            }

            // Évite les doublons si le protocole est revalidé.
            if (Event::query()->where('protocol_item_id', $item->id)->exists()) {
                continue;
            }

            Event::create([
                'type' => EventType::ANOMALIE->value,
                'title' => $this->title($item),
                'description' => $item->observation,
                'status' => EventStatus::A_TRAITER->value,
                'priority' => $this->priority($item),
                'vehicle_id' => $protocol->vehicle_id,
                'material_id' => $item->material_id,
                'protocol_id' => $protocol->id,
                'protocol_item_id' => $item->id,
                'created_by' => $author->id,
            ]);
            $created++;
        }

        return $created;
    }

    private function title(ProtocolItem $item): string
    {
        $label = $item->stateLabel() ?? 'Anomalie';
        $serial = $item->serial_number ? " (n° {$item->serial_number})" : '';

        return "{$item->material_name}{$serial} — {$label}";
    }

    private function priority(ProtocolItem $item): string
    {
        // HS / absent = prioritaire.
        return in_array($item->state, ['hs', 'absent'], true) ? 'haute' : 'normale';
    }
}
