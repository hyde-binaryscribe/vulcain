<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bouteille d'oxygène embarquée (un emplacement de la dotation d'un véhicule).
 * Le niveau est stocké en pression (bar) ; le pourcentage se calcule via la
 * plage min/max. Les changements de bouteille et relevés sont tracés par
 * OxygenBottleLog.
 */
class OxygenBottle extends Model
{
    use BelongsToOrganisation;

    public const STATUS_EN_SERVICE = 'en_service';
    public const STATUS_A_REMPLACER = 'a_remplacer';
    public const STATUS_HORS_SERVICE = 'hors_service';

    protected $fillable = [
        'vehicle_id',
        'label',
        'serial_number',
        'capacity_l',
        'volume_l',
        'pressure_min',
        'pressure_max',
        'current_pressure',
        'status',
        'is_active',
        'last_checked_at',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'capacity_l' => 'decimal:2',
            'volume_l' => 'integer',
            'pressure_min' => 'integer',
            'pressure_max' => 'integer',
            'current_pressure' => 'integer',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'display_order' => 'integer',
        ];
    }

    /**
     * Volume de gaz embarqué (litres) : volume renseigné, sinon estimé depuis la
     * capacité en eau (L) × la pression de service (bar). Null si indéterminable.
     */
    public function gasVolume(): ?int
    {
        if ($this->volume_l !== null) {
            return $this->volume_l;
        }
        if ($this->capacity_l !== null && $this->pressure_max > 0) {
            return (int) round((float) $this->capacity_l * $this->pressure_max);
        }

        return null;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OxygenBottleLog::class)->latest('recorded_at');
    }

    /** Pourcentage de remplissage d'une pression donnée dans une plage min/max. */
    public static function percentOf(?int $pressure, int $min, int $max): ?int
    {
        if ($pressure === null || $max === $min) {
            return null;
        }

        $percent = ($pressure - $min) / ($max - $min) * 100;

        return (int) round(max(0, min(100, $percent)));
    }

    /** Pourcentage de remplissage courant, ou null si pas de relevé. */
    public function percent(): ?int
    {
        return static::percentOf($this->current_pressure, $this->pressure_min, $this->pressure_max);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_A_REMPLACER => 'À remplacer',
            self::STATUS_HORS_SERVICE => 'Hors service',
            default => 'En service',
        };
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_EN_SERVICE, self::STATUS_A_REMPLACER, self::STATUS_HORS_SERVICE];
    }
}
