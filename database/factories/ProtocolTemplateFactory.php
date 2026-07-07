<?php

namespace Database\Factories;

use App\Models\ProtocolTemplate;
use App\Models\Organisation;
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
        return [
            'organisation_id' => Organisation::factory(),
            'vehicle_id' => Vehicle::factory(),
            'name' => 'Protocole '.fake()->randomElement(['quotidien', 'hebdomadaire', 'mensuel']),
            'types' => ['inventaire'],
            'frequency' => 'weekly',
            'is_active' => true,
        ];
    }
}
