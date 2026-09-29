<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tâche persistante rattachée à un véhicule (à faire tant qu'elle n'est pas
 * cochée), assignée par un responsable et réalisée par l'agent.
 */
class VehicleTask extends Model
{
    use BelongsToOrganisation, RecordsActivity;

    protected $fillable = [
        'vehicle_id',
        'created_by',
        'title',
        'notes',
        'done_at',
        'done_by',
    ];

    protected function casts(): array
    {
        return [
            'done_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('done_at');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
