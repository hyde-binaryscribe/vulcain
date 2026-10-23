<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de dotation d'oxygène d'un type de véhicule : un type de bouteille en
 * une certaine quantité, à un emplacement. Définit la répartition O2 attendue.
 */
class VehicleTypeOxygen extends Model
{
    use BelongsToOrganisation;

    protected $table = 'vehicle_type_oxygen';

    protected $fillable = [
        'vehicle_type_id',
        'oxygen_bottle_type_id',
        'quantity',
        'label',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function bottleType(): BelongsTo
    {
        return $this->belongsTo(OxygenBottleType::class, 'oxygen_bottle_type_id');
    }
}
