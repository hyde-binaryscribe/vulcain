<?php

namespace App\Models;

use App\Domain\Fleet\MaintenanceType;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan d'entretien d'une motorisation : une opération périodique (par
 * kilométrage et/ou par durée) qui génère une échéance à la création d'un
 * véhicule.
 */
class MaintenancePlan extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'vehicle_motorization_id',
        'type',
        'label',
        'interval_km',
        'interval_months',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'interval_km' => 'integer',
            'interval_months' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function motorization(): BelongsTo
    {
        return $this->belongsTo(VehicleMotorization::class, 'vehicle_motorization_id');
    }
}
