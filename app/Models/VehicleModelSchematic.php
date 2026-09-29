<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Image de schéma de carrosserie d'un modèle de véhicule, pour une vue donnée.
 */
class VehicleModelSchematic extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'vehicle_model_id',
        'view',
        'image_path',
    ];

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }
}
