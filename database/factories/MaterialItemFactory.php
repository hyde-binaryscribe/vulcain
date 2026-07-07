<?php

namespace Database\Factories;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialItem>
 */
class MaterialItemFactory extends Factory
{
    protected $model = MaterialItem::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'material_id' => Material::factory(),
            'serial_number' => strtoupper(fake()->bothify('SN-#####')),
            'status' => MaterialStatus::CONFORME->value,
        ];
    }
}
