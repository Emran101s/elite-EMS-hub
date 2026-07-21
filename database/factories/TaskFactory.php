<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(4, true)),
            'status' => fake()->randomElement(Task::statuses()),
            'priority' => fake()->randomElement(array_keys(Task::PRIORITIES)),
            'area' => fake()->randomElement(Task::AREAS),
            'due_on' => fake()->dateTimeBetween('-1 week', '+2 months'),
        ];
    }
}
