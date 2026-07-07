<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'type' => 'anomalie',
            'title' => 'Défibrillateur HS',
            'status' => 'a_traiter',
            'priority' => 'normale',
        ];
    }
}
