<?php

namespace App\Models;

use App\Domain\Fleet\ProtocolPhase;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Protocole de service (ouverture / fermeture) applicable selon une portée :
 * un véhicule précis, un modèle, un type, ou par défaut (toutes les colonnes de
 * portée nulles). La résolution se fait en cascade, du plus précis au plus
 * général : véhicule → modèle → type → défaut.
 */
class ServiceProtocol extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'vehicle_type',
        'vehicle_model_id',
        'vehicle_id',
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

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Niveau de portée du protocole : 'vehicle' | 'model' | 'type' | 'default'. */
    public function scope(): string
    {
        return match (true) {
            $this->vehicle_id !== null => 'vehicle',
            $this->vehicle_model_id !== null => 'model',
            $this->vehicle_type !== null => 'type',
            default => 'default',
        };
    }

    /**
     * Protocole applicable à un véhicule pour une phase, résolu en cascade :
     * véhicule précis → modèle → type → défaut. Le premier trouvé l'emporte et
     * remplace entièrement les niveaux plus généraux.
     */
    public static function resolveFor(Vehicle $vehicle, ProtocolPhase $phase): ?self
    {
        $base = static::query()->where('phase', $phase->value)->where('is_active', true)->with('fields');

        // Véhicule précis.
        $specific = (clone $base)->where('vehicle_id', $vehicle->id)->first();
        if ($specific !== null) {
            return $specific;
        }

        // Modèle.
        if ($vehicle->vehicle_model_id !== null) {
            $byModel = (clone $base)->whereNull('vehicle_id')
                ->where('vehicle_model_id', $vehicle->vehicle_model_id)->first();
            if ($byModel !== null) {
                return $byModel;
            }
        }

        // Type.
        if ($vehicle->type !== null && $vehicle->type !== '') {
            $byType = (clone $base)->whereNull('vehicle_id')->whereNull('vehicle_model_id')
                ->where('vehicle_type', $vehicle->type)->first();
            if ($byType !== null) {
                return $byType;
            }
        }

        // Défaut (toutes portées nulles).
        return (clone $base)->whereNull('vehicle_id')->whereNull('vehicle_model_id')
            ->whereNull('vehicle_type')->first();
    }
}
