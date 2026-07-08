<?php

namespace App\Models;

use App\Domain\Billing\Plan;
use App\Domain\Billing\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement d'une organisation. Table centrale (non cloisonnée) — gérée
 * depuis le Desk. Pas de scope organisation : accès plateforme uniquement.
 */
class Subscription extends Model
{
    protected $fillable = [
        'organisation_id',
        'plan',
        'status',
        'trial_ends_at',
        'current_period_end',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_end' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }
}
