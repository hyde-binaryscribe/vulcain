<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mouvement d'un sac d'un véhicule vers un autre.
 */
class BagMovement extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'location_id',
        'from_vehicle_id',
        'to_vehicle_id',
        'user_id',
        'moved_at',
    ];

    protected function casts(): array
    {
        return ['moved_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function fromVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'from_vehicle_id');
    }

    public function toVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'to_vehicle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
