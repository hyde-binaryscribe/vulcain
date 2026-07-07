<?php

namespace App\Domain\Protocol;

use App\Models\Protocol;
use App\Models\ProtocolTemplate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Démarre un protocole en figeant un instantané complet du modèle : les
 * modifications ultérieures du catalogue ou du modèle n'affectent jamais
 * le protocole créé.
 */
class ProtocolSnapshot
{
    public function start(Vehicle $vehicle, ?ProtocolTemplate $template, User $verifier): Protocol
    {
        return DB::transaction(function () use ($vehicle, $template, $verifier) {
            $protocol = Protocol::create([
                'vehicle_id' => $vehicle->id,
                'protocol_template_id' => $template?->id,
                'template_version' => $template?->version,
                'user_id' => $verifier->id,
                'vehicle_name' => $vehicle->name,
                'template_name' => $template?->name,
                'types' => $template?->types,
                'status' => Protocol::STATUS_DRAFT,
                'started_at' => now(),
            ]);

            if ($template !== null) {
                $template->load(['items.material:id,name,reference,tracking_mode', 'items.location:id,name']);

                foreach ($template->items as $templateItem) {
                    $protocol->items()->create([
                        'material_id' => $templateItem->material_id,
                        'material_name' => $templateItem->material?->name ?? 'Matériel',
                        'reference' => $templateItem->material?->reference,
                        'location_name' => $templateItem->location?->name,
                        'tracking_mode' => $templateItem->material?->tracking_mode ?? 'quantity',
                        'expected_qty' => $templateItem->expected_qty,
                        'photo_required' => $templateItem->photo_required,
                        'display_order' => $templateItem->display_order,
                    ]);
                }
            }

            return $protocol;
        });
    }
}
