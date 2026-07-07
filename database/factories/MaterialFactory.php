<?php

namespace Database\Factories;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Material;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'reference' => strtoupper(fake()->bothify('???-###')),
            'name' => fake()->randomElement(['Collier cervical adulte', 'Couverture de survie', 'BAVU adulte', 'Attelle', 'Compresses']),
            'tracking_mode' => 'quantity',
            'theoretical_qty' => fake()->numberBetween(1, 10),
            'minimum_qty' => fake()->numberBetween(0, 2),
            'status' => MaterialStatus::CONFORME->value,
        ];
    }
}
