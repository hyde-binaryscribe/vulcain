<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Session d'un véhicule pendant le service : ouverte par un agent après la
 * vérification de prise de service, clôturée manuellement (fin de service) ou
 * automatiquement lorsqu'un autre agent prend le véhicule (passation).
 */
class VehicleSession extends Model
{
    use BelongsToOrganisation, RecordsActivity;

    public const REASON_MANUAL = 'manual';

    public const REASON_HANDOVER = 'handover';

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'opened_at',
        'open_mileage',
        'open_steps',
        'open_responses',
        'open_notes',
        'closed_at',
        'closed_by',
        'close_mileage',
        'close_notes',
        'close_responses',
        'close_reason',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'open_steps' => 'array',
            'open_responses' => 'array',
            'close_responses' => 'array',
            'open_mileage' => 'integer',
            'close_mileage' => 'integer',
        ];
    }

    /** Sessions encore ouvertes. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Agent ayant pris le service. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Agent ayant clôturé (manuellement ou par passation). */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
