<?php

namespace Database\Factories;

use App\Models\MaterialCategory;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialCategory>
 */
class MaterialCategoryFactory extends Factory
{
    protected $model = MaterialCategory::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->unique()->randomElement(['Immobilisation', 'Oxygénothérapie', 'Secours à personne', 'Pansements', 'Diagnostic']),
        ];
    }
}
