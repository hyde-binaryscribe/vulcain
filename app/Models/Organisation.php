<?php

namespace App\Models;

use App\Domain\Sectors\Sector;
use App\Domain\Sectors\SectorProfile;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Organisation cliente = tenant du SaaS (un centre de secours / SDIS,
 * une société d'ambulance, une association agréée de sécurité civile…).
 * Table centrale : ce modèle n'est PAS cloisonné.
 */
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'group_id',
        'name',
        'slug',
        'sector',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'sector' => Sector::class,
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Profil du secteur (vocabulaire, branding). */
    public function profile(): SectorProfile
    {
        return ($this->sector ?? Sector::default())->profile();
    }

    /**
     * Suit-on les péremptions dans les emplacements mobiles (véhicules) ?
     * Activé par défaut ; le propriétaire peut désactiver (suivi jugé trop
     * lourd à tenir à bord).
     */
    public function tracksExpiryInMobile(): bool
    {
        return (bool) ($this->settings['track_expiry_in_mobile'] ?? true);
    }
}
