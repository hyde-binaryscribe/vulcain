<?php

namespace App\Domain\Protocol;

use App\Models\Material;
use App\Models\Protocol;
use App\Models\ProtocolTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Démarre un protocole en figeant un instantané complet du périmètre : les
 * matériels du périmètre (véhicule ou emplacement + enfants, moins les
 * exclusions) sont copiés au démarrage. Les modifications ultérieures du
 * catalogue ou du modèle n'affectent jamais le protocole créé.
 */
class ProtocolSnapshot
{
    public function __construct(private readonly ProtocolScope $scope) {}

    public function start(ProtocolTemplate $template, User $verifier): Protocol
    {
        return DB::transaction(function () use ($template, $verifier) {
            $vehicle = $this->scope->vehicle($template);

            $protocol = Protocol::create([
                'vehicle_id' => $vehicle?->id,
                'protocol_template_id' => $template->id,
                'template_version' => $template->version,
                'user_id' => $verifier->id,
                'vehicle_name' => $this->scope->targetLabel($template),
                'template_name' => $template->name,
                'types' => $template->types,
                'status' => Protocol::STATUS_DRAFT,
                'started_at' => now(),
            ]);

            $order = 0;
            foreach ($this->scope->materials($template) as $material) {
                $protocol->items()->create([
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'reference' => $material->reference,
                    'location_name' => $material->location?->fullPath(),
                    'tracking_mode' => $material->tracking_mode,
                    'expected_qty' => $this->expectedQty($material),
                    'display_order' => $order += 10,
                ]);
            }

            return $protocol;
        });
    }

    private function expectedQty(Material $material): int
    {
        return match ($material->tracking_mode) {
            Material::MODE_QUANTITY => (int) ($material->theoretical_qty ?: 0),
            default => (int) $material->stockQuantity(),
        };
    }
}
