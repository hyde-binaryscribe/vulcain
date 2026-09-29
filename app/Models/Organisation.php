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

    /**
     * Active la nature d'emplacement « Sac » (sacs de secours à bord).
     * Désactivée par défaut : à activer par l'organisation qui en a l'usage.
     */
    public function bagsEnabled(): bool
    {
        return (bool) ($this->settings['bags_enabled'] ?? false);
    }

    /**
     * Accès aux véhicules réservé au scan du QR : le personnel de terrain ne
     * voit pas la liste des véhicules et ouvre une fiche uniquement en scannant
     * le QR placé à bord. Les gestionnaires (vehicles.manage) gardent la liste.
     */
    public function vehicleAccessQrOnly(): bool
    {
        return (bool) ($this->settings['vehicle_access_qr_only'] ?? false);
    }

    /** Suivi du carburant (pleins + consommation), activable par l'organisation. */
    public function fuelTrackingEnabled(): bool
    {
        return (bool) ($this->settings['fuel_tracking_enabled'] ?? true);
    }

    /** Colonne Kanban où atterrissent les nouvelles anomalies (ou null → 1re colonne). */
    public function anomalyEntryColumnId(): ?int
    {
        $id = $this->settings['anomaly_entry_column_id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    /** Procédure de prise de service par défaut (secteur secours). */
    public const DEFAULT_SERVICE_START_STEPS = [
        'Contrôle des niveaux (huile, liquide de refroidissement)',
        'État et pression des pneumatiques',
        'Feux, gyrophares et avertisseurs',
        'Carburant / autonomie suffisante',
        'Propreté et désinfection cabine et cellule',
        'Oxygène : pression des bouteilles',
        'Matériel électro (DAE, scope) présent et chargé',
        'Matériel obligatoire présent et non périmé',
    ];

    /**
     * Étapes de la procédure de prise de service (configurable par l'organisation).
     *
     * @return list<string>
     */
    public function serviceStartSteps(): array
    {
        $steps = $this->settings['service_start_steps'] ?? null;

        if (! is_array($steps)) {
            return self::DEFAULT_SERVICE_START_STEPS;
        }

        $steps = array_values(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $steps,
        ), fn ($s) => $s !== ''));

        return $steps !== [] ? $steps : self::DEFAULT_SERVICE_START_STEPS;
    }
}
