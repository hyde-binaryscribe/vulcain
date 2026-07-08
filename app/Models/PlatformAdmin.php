<?php

namespace App\Models;

use Database\Factories\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Administrateur de la plateforme (exploitant SaaS). Guard « platform »,
 * strictement séparé des utilisateurs des organisations.
 */
class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'group_id',
        'name',
        'email',
        'password',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** Gestionnaire de groupe (périmètre limité) vs exploitant global. */
    public function isGroupManager(): bool
    {
        return $this->group_id !== null;
    }

    /**
     * Peut-il gérer cette organisation ? L'exploitant global gère tout ; un
     * gestionnaire de groupe uniquement les organisations de son groupe.
     */
    public function canManageOrganisation(Organisation $organisation): bool
    {
        return $this->group_id === null || $organisation->group_id === $this->group_id;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }
}
