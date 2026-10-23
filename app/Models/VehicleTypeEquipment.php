<?php

namespace App\Models;

use App\Domain\Fleet\ArmamentLevel;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'armement de référence d'un type de véhicule : un équipement attendu
 * (catégorie, désignation, quantité) avec son niveau d'obligation.
 */
class VehicleTypeEquipment extends Model
{
    use BelongsToOrganisation;

    protected $table = 'vehicle_type_equipment';

    protected $fillable = [
        'vehicle_type_id',
        'category',
        'designation',
        'quantity',
        'level',
        'note',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'level' => ArmamentLevel::class,
            'display_order' => 'integer',
        ];
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }
}
