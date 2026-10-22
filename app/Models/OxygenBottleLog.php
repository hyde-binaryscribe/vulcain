<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrée de journal d'une bouteille d'oxygène : relevé de niveau, changement de
 * bouteille (remplacement) ou changement de statut. Trace l'agent et la date.
 */
class OxygenBottleLog extends Model
{
    use BelongsToOrganisation;

    public const ACTION_RELEVE = 'releve';
    public const ACTION_REMPLACEMENT = 'remplacement';
    public const ACTION_STATUT = 'statut';

    protected $fillable = [
        'oxygen_bottle_id',
        'vehicle_id',
        'user_id',
        'action',
        'pressure',
        'percent',
        'serial_number',
        'previous_serial',
        'note',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'pressure' => 'integer',
            'percent' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function bottle(): BelongsTo
    {
        return $this->belongsTo(OxygenBottle::class, 'oxygen_bottle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_REMPLACEMENT => 'Changement de bouteille',
            self::ACTION_STATUT => 'Changement de statut',
            default => 'Relevé de niveau',
        };
    }
}
