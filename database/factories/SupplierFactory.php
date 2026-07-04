<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'category' => fake()->randomElement(Supplier::CATEGORIES),
            'rating' => fake()->randomFloat(1, 3.5, 5.0),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'country' => fake()->randomElement(['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA']),
        ];
    }
}
