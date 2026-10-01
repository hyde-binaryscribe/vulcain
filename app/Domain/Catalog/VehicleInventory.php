<?php

namespace App\Domain\Catalog;

use App\Models\Material;
use App\Models\MaterialConsumption;
use App\Models\MaterialItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use Illuminate\Support\Carbon;

/**
 * Logique d'inventaire embarqué : sortie de consommables (décrément du stock
 * selon le mode de suivi) et réarmement (remise au niveau théorique — pointage
 * simple, sans stock central).
 */
final class VehicleInventory
{
    /** Manque par rapport au niveau théorique (0 si complet). */
    public static function missing(Material $material): int
    {
        return max(0, (int) $material->theoretical_qty - $material->stockQuantity());
    }

    /**
     * Enregistre une sortie de consommable et décrémente le stock du véhicule.
     */
    public static function consume(
        Vehicle $vehicle,
        Material $material,
        int $quantity,
        ?MaterialItem $item,
        ?User $user,
        ?VehicleSession $session,
        ?string $notes = null,
    ): MaterialConsumption {
        $quantity = max(1, $quantity);
        $serial = null;
        $lotId = null;
        $itemId = null;

        switch ($material->tracking_mode) {
            case Material::MODE_SERIAL:
                // Sortie d'un exemplaire (celui choisi, sinon le plus ancien).
                $target = $item ?? $material->items()->orderBy('id')->first();
                if ($target !== null) {
                    $serial = $target->serial_number;
                    $itemId = $target->id;
                    $target->delete(); // soft delete : ne compte plus dans le stock
                }
                $quantity = 1;
                break;

            case Material::MODE_LOT:
                // FEFO : on puise d'abord dans le lot qui périme le plus tôt.
                $remaining = $quantity;
                $lots = $material->lots()
                    ->where('quantity', '>', 0)
                    ->orderByRaw('expiry_date is null') // dates de péremption d'abord
                    ->orderBy('expiry_date')
                    ->get();
                foreach ($lots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($remaining, (int) $lot->quantity);
                    $lot->quantity = (int) $lot->quantity - $take;
                    $remaining -= $take;
                    $lotId ??= $lot->id;
                    if ($lot->quantity <= 0) {
                        $lot->delete();
                    } else {
                        $lot->save();
                    }
                }
                break;

            default: // MODE_QUANTITY
                $material->current_qty = max(0, (int) $material->current_qty - $quantity);
                $material->save();
                break;
        }

        return MaterialConsumption::create([
            'vehicle_id' => $vehicle->id,
            'material_id' => $material->id,
            'material_item_id' => $itemId,
            'stock_lot_id' => $lotId,
            'vehicle_session_id' => $session?->id,
            'user_id' => $user?->id,
            'quantity' => $quantity,
            'serial_number' => $serial,
            'consumed_at' => Carbon::now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Réarmement (pointage simple) : remet le matériel au niveau théorique.
     * Aucun stock central n'est décrémenté.
     */
    public static function restock(Material $material): void
    {
        $missing = self::missing($material);
        if ($missing <= 0) {
            return;
        }

        switch ($material->tracking_mode) {
            case Material::MODE_SERIAL:
                for ($i = 0; $i < $missing; $i++) {
                    $material->items()->create([
                        'location_id' => $material->location_id,
                        'serial_number' => null, // à renseigner (exemplaire ré-embarqué)
                        'status' => MaterialStatus::CONFORME->value,
                    ]);
                }
                break;

            case Material::MODE_LOT:
                // Pointage simple : lot de réarmement sans péremption (à préciser en pharmacie).
                $material->lots()->create([
                    'location_id' => $material->location_id,
                    'lot_number' => 'Réarmement '.Carbon::now()->format('d/m/Y'),
                    'quantity' => $missing,
                    'status' => MaterialStatus::CONFORME->value,
                ]);
                break;

            default: // MODE_QUANTITY
                $material->current_qty = (int) $material->theoretical_qty;
                $material->save();
                break;
        }
    }
}
