<?php

namespace Database\Factories;

use App\Models\Protocol;
use App\Models\ProtocolItem;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProtocolItem>
 */
class ProtocolItemFactory extends Factory
{
    protected $model = ProtocolItem::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'protocol_id' => Protocol::factory(),
            'material_name' => 'Collier cervical adulte',
            'tracking_mode' => 'quantity',
            'expected_qty' => 4,
            'display_order' => 0,
        ];
    }
}
