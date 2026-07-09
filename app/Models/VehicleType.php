<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
}
