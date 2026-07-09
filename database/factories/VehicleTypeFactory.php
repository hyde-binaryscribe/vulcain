<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    protected $model = VehicleType::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->unique()->lexify('Type ???'),
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
