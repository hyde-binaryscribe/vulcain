<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => 'CIS '.fake()->unique()->city(),
            'kind' => 'centre',
            'is_active' => true,
        ];
    }
}
