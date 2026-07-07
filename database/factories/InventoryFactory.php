<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'vehicle_id' => Vehicle::factory(),
            'user_id' => User::factory(),
            'vehicle_name' => 'VSAV 01',
            'status' => Inventory::STATUS_DRAFT,
            'started_at' => now(),
        ];
    }

    public function validated(): static
    {
        return $this->state(fn () => [
            'status' => Inventory::STATUS_VALIDATED,
            'validated_at' => now(),
            'duration_seconds' => 300,
        ]);
    }
}
