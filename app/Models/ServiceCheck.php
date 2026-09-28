<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prise de service d'un véhicule par un agent : relevé kilométrique + procédure
 * de prise de service (checklist) horodatés.
 */
class ServiceCheck extends Model
{
    use BelongsToOrganisation, RecordsActivity;

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'mileage',
        'steps',
        'notes',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'mileage' => 'integer',
            'started_at' => 'datetime',
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
