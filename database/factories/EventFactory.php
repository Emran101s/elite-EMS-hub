<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = fake()->dateTimeBetween('now', '+6 months');

        return [
            'name' => fake()->company().' '.fake()->randomElement(['Summit', 'Forum', 'Expo', 'Gala', 'Workshop']),
            'type' => fake()->randomElement(Event::TYPES),
            'city' => fake()->city(),
            'country' => fake()->randomElement(['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA']),
            'starts_at' => $starts,
            'ends_at' => (clone $starts)->modify('+2 days'),
            'budget_cents' => fake()->numberBetween(50, 500) * 100000,
            'progress' => fake()->numberBetween(10, 95),
        ];
    }
}
