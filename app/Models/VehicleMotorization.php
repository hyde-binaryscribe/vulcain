<?php

namespace App\Models;

use App\Domain\Fleet\MaintenanceType;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Motorisation d'un modèle de véhicule (ex. « 2.3 dCi 145 ch »), porteuse de
 * ses plans d'entretien. À la création d'un véhicule sur cette motorisation,
 * les échéances d'entretien sont générées à partir de ces plans.
 */
class VehicleMotorization extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'vehicle_model_id',
        'name',
        'fuel',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class)->orderBy('display_order');
    }

    /**
     * Génère les échéances d'entretien d'un véhicule à partir des plans de la
     * motorisation. Une échéance « socle » est créée par plan, calée sur la
     * mise en service (ou aujourd'hui) et le kilométrage courant du véhicule.
     *
     * Idempotent : on ignore les types déjà couverts par une échéance existante.
     *
     * @return int Nombre d'échéances créées.
     */
    public function generateMaintenanceFor(Vehicle $vehicle): int
    {
        $plans = $this->maintenancePlans()->get();
        if ($plans->isEmpty()) {
            return 0;
        }

        $existingTypes = MaintenanceRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->pluck('type')
            ->map(fn ($t) => $t instanceof MaintenanceType ? $t->value : (string) $t)
            ->all();

        $baseDate = $vehicle->commissioned_at !== null
            ? Carbon::parse($vehicle->commissioned_at)
            : Carbon::today();
        $baseMileage = $vehicle->mileage !== null ? (int) $vehicle->mileage : 0;

        $created = 0;

        foreach ($plans as $plan) {
            if ($plan->interval_km === null && $plan->interval_months === null) {
                continue; // plan sans périodicité : rien à échoir
            }
            if (in_array($plan->type->value, $existingTypes, true)) {
                continue; // type déjà suivi : on ne duplique pas
            }

            MaintenanceRecord::create([
                'vehicle_id' => $vehicle->id,
                'user_id' => null,
                'type' => $plan->type->value,
                'performed_at' => $baseDate,
                'mileage' => $baseMileage,
                'provider' => null,
                'notes' => 'Échéance générée depuis le plan d’entretien'.($plan->label ? " ({$plan->label})" : ''),
                'next_due_at' => $plan->interval_months !== null ? $baseDate->copy()->addMonths($plan->interval_months) : null,
                'next_due_mileage' => $plan->interval_km !== null ? $baseMileage + $plan->interval_km : null,
            ]);
            $existingTypes[] = $plan->type->value;
            $created++;
        }

        return $created;
    }
}
