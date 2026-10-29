<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Resource> */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'name' => fake()->words(3, true),
            'resource_code' => 'RES-'.fake()->unique()->numerify('####'),
            'type' => 'material',
            'quantity_available' => fake()->numberBetween(1, 100),
            'condition' => 'good',
            'created_by' => $user,
            'updated_by' => $user,
        ];
    }
}
