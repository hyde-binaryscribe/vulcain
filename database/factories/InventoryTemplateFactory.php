<?php

namespace Database\Factories;

use App\Models\InventoryTemplate;
use App\Models\Organisation;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTemplate>
 */
class InventoryTemplateFactory extends Factory
{
    protected $model = InventoryTemplate::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'vehicle_id' => Vehicle::factory(),
            'name' => 'Inventaire '.fake()->randomElement(['quotidien', 'hebdomadaire', 'mensuel']),
            'frequency' => 'weekly',
            'is_active' => true,
        ];
    }
}
