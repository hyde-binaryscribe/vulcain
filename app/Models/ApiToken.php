<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Jeton d'accès à l'API v1 (lien Itineris), limité à une organisation.
 * Seule l'empreinte est stockée ; le jeton en clair n'existe qu'à la création.
 */
class ApiToken extends Model
{
    use BelongsToOrganisation;

    /** Préfixe lisible : repère un jeton Vulkain dans une configuration. */
    public const PREFIX = 'vk_';

    protected $fillable = ['name', 'token_hash', 'last_four', 'created_by', 'last_used_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Crée un jeton pour l'organisation courante.
     *
     * @return array{0: ApiToken, 1: string} le jeton et sa valeur en clair (à montrer une seule fois)
     */
    public static function issue(string $name, ?int $createdBy): array
    {
        $plain = self::PREFIX.Str::random(48);
        $token = self::create([
            'name' => $name,
            'token_hash' => self::hash($plain),
            'last_four' => substr($plain, -4),
            'created_by' => $createdBy,
        ]);

        return [$token, $plain];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
