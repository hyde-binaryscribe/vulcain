<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tentative de connexion (réussie ou échouée). Alimente le blocage temporaire
 * et la traçabilité. Table volontairement non cloisonnée (organisation_id nullable).
 */
class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organisation_id',
        'email',
        'ip_address',
        'user_agent',
        'successful',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
