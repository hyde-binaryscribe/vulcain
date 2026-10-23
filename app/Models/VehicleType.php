<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Type de véhicule du catalogue de l'organisation (VSAV, Ambulance type A…).
 * Le véhicule stocke son type par libellé (vehicles.type) ; ce catalogue
 * fournit la liste imposée dans le formulaire véhicule.
 */
class VehicleType extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'display_order',
        'is_active',
        'disinfection_interval_days',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'disinfection_interval_days' => 'integer',
        ];
    }

    /**
     * Amorce le catalogue depuis les suggestions du secteur si l'organisation
     * n'en possède aucun (l'admin peut ensuite l'éditer librement).
     */
    public static function ensureSeeded(Organisation $organisation): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach ($organisation->profile()->vehicleTypes() as $order => $name) {
            static::create(['name' => $name, 'display_order' => $order]);
        }
    }

    /** Armement de référence (équipements attendus) de ce type. */
    public function equipment(): HasMany
    {
        return $this->hasMany(VehicleTypeEquipment::class);
    }

    /** Dotation d'oxygène de référence (répartition en bouteilles) de ce type. */
    public function oxygenDotation(): HasMany
    {
        return $this->hasMany(VehicleTypeOxygen::class)->orderBy('display_order')->orderBy('id');
    }
}
