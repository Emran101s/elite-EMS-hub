<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'status' => fake()->randomElement(Project::STATUSES),
            'description' => fake()->sentence(10),
            'starts_on' => fake()->dateTimeBetween('-2 months', 'now'),
            'ends_on' => fake()->dateTimeBetween('+1 month', '+8 months'),
            'budget_cents' => fake()->numberBetween(100, 800) * 100000,
        ];
    }
}
