<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\ProtocolTemplate;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProtocolTemplate>
 */
class ProtocolTemplateFactory extends Factory
{
    protected $model = ProtocolTemplate::class;

    public function definition(): array
    {
        $vehicle = Vehicle::factory();

        return [
            'organisation_id' => Organisation::factory(),
            'vehicle_id' => $vehicle,
            'name' => 'Protocole '.fake()->randomElement(['quotidien', 'hebdomadaire', 'mensuel']),
            'types' => ['inventaire'],
            'scope_type' => 'vehicle',
            'include_children' => true,
            'frequency' => 'weekly',
            'is_active' => true,
        ];
    }

    /** Cible = un véhicule (scope_id aligné sur vehicle_id). */
    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn () => [
            'vehicle_id' => $vehicle->id,
            'scope_type' => 'vehicle',
            'scope_id' => $vehicle->id,
        ]);
    }
}
