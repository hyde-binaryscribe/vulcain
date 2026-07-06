<?php

namespace Database\Factories;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    public function definition(): array
    {
        $name = 'CIS '.fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'status' => Organisation::STATUS_ACTIVE,
            'settings' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Organisation::STATUS_SUSPENDED]);
    }

    public function slug(string $slug): static
    {
        return $this->state(fn () => ['slug' => $slug]);
    }
}
