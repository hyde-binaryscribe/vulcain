<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation à rejoindre une organisation. Gérée de façon inter-tenant
 * (créée par l'exploitant, acceptée sur le sous-domaine de l'organisation),
 * donc organisation_id est renseigné explicitement (pas via le scope global).
 */
class Invitation extends Model
{
    protected $fillable = [
        'organisation_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
