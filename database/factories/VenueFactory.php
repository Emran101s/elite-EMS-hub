<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' '.fake()->randomElement(['Convention Center', 'Grand Hall', 'Hotel Ballroom', 'Exhibition Center']),
            'city' => fake()->city(),
            'country' => fake()->randomElement(['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA']),
            'capacity' => fake()->numberBetween(100, 5000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
