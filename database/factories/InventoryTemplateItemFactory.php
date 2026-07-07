<?php

namespace Database\Factories;

use App\Models\InventoryTemplate;
use App\Models\InventoryTemplateItem;
use App\Models\Material;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTemplateItem>
 */
class InventoryTemplateItemFactory extends Factory
{
    protected $model = InventoryTemplateItem::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'inventory_template_id' => InventoryTemplate::factory(),
            'material_id' => Material::factory(),
            'expected_qty' => fake()->numberBetween(1, 10),
            'display_order' => fake()->numberBetween(0, 100),
            'photo_required' => false,
        ];
    }
}
