<?php

namespace Database\Factories;

use App\Models\ProtocolTemplate;
use App\Models\ProtocolTemplateItem;
use App\Models\Material;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProtocolTemplateItem>
 */
class ProtocolTemplateItemFactory extends Factory
{
    protected $model = ProtocolTemplateItem::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'protocol_template_id' => ProtocolTemplate::factory(),
            'material_id' => Material::factory(),
            'expected_qty' => fake()->numberBetween(1, 10),
            'display_order' => fake()->numberBetween(0, 100),
            'photo_required' => false,
        ];
    }
}
