<?php

namespace App\Models;

use App\Domain\Fleet\ProtocolPhase;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Protocole de service (ouverture / fermeture) pour un type de véhicule donné
 * (ou par défaut si vehicle_type est nul).
 */
class ServiceProtocol extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'vehicle_type',
        'phase',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'phase' => ProtocolPhase::class,
            'is_active' => 'boolean',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ServiceProtocolField::class)->orderBy('display_order');
    }

    /**
     * Protocole applicable à un type de véhicule pour une phase : protocole
     * spécifique au type, sinon protocole par défaut (vehicle_type nul).
     */
    public static function resolve(?string $vehicleType, ProtocolPhase $phase): ?self
    {
        $query = static::query()->where('phase', $phase->value)->where('is_active', true)->with('fields');

        if ($vehicleType !== null) {
            $specific = (clone $query)->where('vehicle_type', $vehicleType)->first();
            if ($specific !== null) {
                return $specific;
            }
        }

        return (clone $query)->whereNull('vehicle_type')->first();
    }
}
