<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Adresse de notification sortante (webhook) d'une organisation. Chaque envoi
 * est signé avec le secret (HMAC SHA-256), stocké chiffré et montré une fois.
 */
class WebhookEndpoint extends Model
{
    use BelongsToOrganisation;

    /** Préfixe lisible du secret de signature. */
    public const SECRET_PREFIX = 'whsec_';

    /** Événements émis (docs/LIEN-INTRA-CHATEL.md). */
    public const EVENTS = ['service.opened', 'service.closed'];

    protected $fillable = ['name', 'url', 'secret', 'events', 'is_active', 'created_by', 'last_success_at', 'last_failure_at', 'last_error'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public static function generateSecret(): string
    {
        return self::SECRET_PREFIX.Str::random(40);
    }

    /** L'adresse reçoit-elle cet événement ? (« ping » : toujours.) */
    public function listensTo(string $event): bool
    {
        return $event === 'ping' || empty($this->events) || in_array($event, $this->events, true);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
