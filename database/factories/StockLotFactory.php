<?php

namespace Database\Factories;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\StockLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLot>
 */
class StockLotFactory extends Factory
{
    protected $model = StockLot::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'material_id' => Material::factory(),
            'lot_number' => strtoupper(fake()->bothify('LOT-####')),
            'quantity' => fake()->numberBetween(1, 20),
            'expiry_date' => now()->addMonths(fake()->numberBetween(1, 24))->toDateString(),
            'status' => MaterialStatus::CONFORME->value,
        ];
    }
}
