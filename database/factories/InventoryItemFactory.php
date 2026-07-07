<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'inventory_id' => Inventory::factory(),
            'material_name' => 'Collier cervical adulte',
            'tracking_mode' => 'quantity',
            'expected_qty' => 4,
            'display_order' => 0,
        ];
    }
}
