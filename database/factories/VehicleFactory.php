<?php

namespace Database\Factories;

use App\Domain\Fleet\VehicleStatus;
use App\Models\Organisation;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['VSAV', 'FPT', 'VTU', 'VL']);

        return [
            'organisation_id' => Organisation::factory(),
            'name' => $type.' '.fake()->numberBetween(1, 9),
            'type' => $type,
            'callsign' => strtoupper($type).'-'.fake()->numberBetween(10, 99),
            'registration' => strtoupper(fake()->bothify('??-###-??')),
            'status' => VehicleStatus::DISPONIBLE->value,
            'mileage' => fake()->numberBetween(0, 150000),
        ];
    }
}
