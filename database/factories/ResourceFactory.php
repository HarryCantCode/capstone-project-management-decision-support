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
            'resource_code' => 'DEX-MAT' . fake()->unique()->numberBetween(100, 999),
            'type' => 'material',
            'model' => fake()->sentence(4),
            'quantity_available' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 50000, 500000),
            'unit_cost' => fake()->randomFloat(2, 40000, 450000),
            'demand_status' => fake()->randomElement(['fast-moving', 'slow-moving']),
            'description' => fake()->sentence(8),
            'condition' => 'good',
            'created_by' => $user,
            'updated_by' => $user,
        ];
    }
}
