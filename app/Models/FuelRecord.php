<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plein de carburant d'un véhicule (litrage, kilométrage, coût optionnel).
 */
class FuelRecord extends Model
{
    use BelongsToOrganisation, RecordsActivity;

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'filled_at',
        'mileage',
        'liters',
        'cost',
        'full_tank',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'filled_at' => 'datetime',
            'mileage' => 'integer',
            'liters' => 'decimal:2',
            'cost' => 'decimal:2',
            'full_tank' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
