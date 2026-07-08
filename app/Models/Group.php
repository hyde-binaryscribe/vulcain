<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Groupe (entreprise) regroupant plusieurs organisations. Entité plateforme
 * (centrale, non cloisonnée) gérée depuis le Desk.
 */
class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class);
    }

    public function managers(): HasMany
    {
        return $this->hasMany(PlatformAdmin::class);
    }
}
