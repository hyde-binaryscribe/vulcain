<?php

namespace Database\Factories;

use App\Models\MaterialType;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialType>
 */
class MaterialTypeFactory extends Factory
{
    protected $model = MaterialType::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'category_id' => null,
            'name' => fake()->unique()->words(2, true),
            'tracking_mode' => 'serial',
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
