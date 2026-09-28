<?php

namespace Database\Factories;

use App\Domain\Fleet\DisinfectionType;
use App\Models\DisinfectionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisinfectionRecord>
 *
 * Le vehicle_id doit être fourni par le test ; organisation_id est renseigné
 * automatiquement depuis le contexte de tenant.
 */
class DisinfectionRecordFactory extends Factory
{
    protected $model = DisinfectionRecord::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(DisinfectionType::cases())->value,
            'performed_at' => now()->subDays(fake()->numberBetween(0, 20)),
            'notes' => null,
        ];
    }
}
