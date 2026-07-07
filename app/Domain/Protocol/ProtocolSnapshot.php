<?php

namespace App\Domain\Protocol;

use App\Models\Material;
use App\Models\Protocol;
use App\Models\ProtocolTemplate;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Démarre un protocole en figeant un instantané complet du périmètre. Chaque
 * matériel est éclaté selon sa nature :
 *  - série     : une ligne PAR n° de série attendu (présence à vérifier) ;
 *  - consommable : une ligne (à compter + péremption la plus proche) ;
 *  - quantité  : une ligne (+/-).
 * Les modifications ultérieures du catalogue n'affectent jamais le protocole.
 */
class ProtocolSnapshot
{
    public function __construct(
        private readonly ProtocolScope $scope,
        private readonly TenantContext $tenant,
    ) {}

    public function start(ProtocolTemplate $template, User $verifier): Protocol
    {
        return DB::transaction(function () use ($template, $verifier) {
            $vehicle = $this->scope->vehicle($template);
            $trackExpiryInMobile = $this->tenant->organisation()->tracksExpiryInMobile();

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
                $order = $this->appendMaterial($protocol, $material, $trackExpiryInMobile, $order);
            }

            return $protocol;
        });
    }

    private function appendMaterial(Protocol $protocol, Material $material, bool $trackExpiryInMobile, int $order): int
    {
        $base = [
            'material_id' => $material->id,
            'material_name' => $material->name,
            'reference' => $material->reference,
            'location_name' => $material->location?->fullPath(),
            'tracking_mode' => $material->tracking_mode,
        ];

        if ($material->tracking_mode === Material::MODE_SERIAL) {
            // Une ligne par exemplaire (n° de série) attendu à son emplacement.
            foreach ($material->items as $unit) {
                $protocol->items()->create([
                    ...$base,
                    'serial_number' => $unit->serial_number,
                    'location_name' => $unit->location?->fullPath() ?? $material->location?->fullPath(),
                    'expected_qty' => 1,
                    'display_order' => $order += 10,
                ]);
            }

            return $order;
        }

        if ($material->tracking_mode === Material::MODE_LOT) {
            $protocol->items()->create([
                ...$base,
                'expected_qty' => (int) $material->stockQuantity(),
                'last_known_expiry' => $this->nearestExpiry($material),
                'expiry_required' => $this->expiryRequired($material, $trackExpiryInMobile),
                'display_order' => $order += 10,
            ]);

            return $order;
        }

        // Quantité simple.
        $protocol->items()->create([
            ...$base,
            'expected_qty' => (int) ($material->theoretical_qty ?: 0),
            'display_order' => $order += 10,
        ]);

        return $order;
    }

    /** Péremption la plus proche connue parmi les lots (repère pour le contrôleur). */
    private function nearestExpiry(Material $material): ?string
    {
        $dates = $material->lots
            ->pluck('expiry_date')
            ->filter()
            ->sort()
            ->values();

        return $dates->first()?->toDateString();
    }

    /**
     * La péremption doit-elle être saisie ? Toujours pour un emplacement fixe ;
     * en mobile (véhicule), seulement si l'organisation suit les péremptions à bord.
     */
    private function expiryRequired(Material $material, bool $trackExpiryInMobile): bool
    {
        if ($material->location?->isMobile()) {
            return $trackExpiryInMobile;
        }

        return true;
    }
}
