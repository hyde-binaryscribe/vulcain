<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position télématique d'un véhicule (géoloc + attributs OBD), transférée par
 * Traccar au webhook d'ingestion.
 */
class VehiclePosition extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'vehicle_id',
        'latitude',
        'longitude',
        'speed',
        'course',
        'altitude',
        'valid',
        'attributes',
        'device_time',
        'server_time',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'speed' => 'integer',
            'course' => 'integer',
            'altitude' => 'integer',
            'valid' => 'boolean',
            'attributes' => 'array',
            'device_time' => 'datetime',
            'server_time' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
