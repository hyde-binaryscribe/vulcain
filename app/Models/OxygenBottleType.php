<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

/**
 * Type de bouteille d'oxygène du catalogue (B2, B5, B11, B15…) : caractéristiques
 * propres (capacité en eau, pression, volume de gaz) servant à pré-remplir une
 * bouteille à la déclaration.
 */
class OxygenBottleType extends Model
{
    use BelongsToOrganisation;

    /** Presets standards (contenance en eau × 200 bar = volume de gaz). */
    public const PRESETS = [
        ['name' => 'B2', 'capacity_l' => 2, 'pressure_min' => 0, 'pressure_max' => 200, 'volume_l' => 400],
        ['name' => 'B5', 'capacity_l' => 5, 'pressure_min' => 0, 'pressure_max' => 200, 'volume_l' => 1000],
        ['name' => 'B11', 'capacity_l' => 11, 'pressure_min' => 0, 'pressure_max' => 200, 'volume_l' => 2200],
        ['name' => 'B15', 'capacity_l' => 15, 'pressure_min' => 0, 'pressure_max' => 200, 'volume_l' => 3000],
    ];

    protected $fillable = [
        'name',
        'capacity_l',
        'pressure_min',
        'pressure_max',
        'volume_l',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity_l' => 'decimal:2',
            'pressure_min' => 'integer',
            'pressure_max' => 'integer',
            'volume_l' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Amorce le catalogue avec les presets standards si l'organisation n'en a aucun. */
    public static function ensureSeeded(): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach (self::PRESETS as $order => $preset) {
            static::create($preset + ['display_order' => $order]);
        }
    }
}
