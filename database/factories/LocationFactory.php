<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->randomElement(['Cellule sanitaire', 'Coffre gauche', 'Coffre droit', 'Sac rouge', 'Cabine', 'Réserve']),
            'kind' => 'fixe',
            'display_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
