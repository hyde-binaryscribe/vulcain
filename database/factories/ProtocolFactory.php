<?php

namespace Database\Factories;

use App\Models\Protocol;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Protocol>
 */
class ProtocolFactory extends Factory
{
    protected $model = Protocol::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'vehicle_id' => Vehicle::factory(),
            'user_id' => User::factory(),
            'vehicle_name' => 'VSAV 01',
            'status' => Protocol::STATUS_DRAFT,
            'started_at' => now(),
        ];
    }

    public function validated(): static
    {
        return $this->state(fn () => [
            'status' => Protocol::STATUS_VALIDATED,
            'validated_at' => now(),
            'duration_seconds' => 300,
        ]);
    }
}
