<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Farmer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farmer_id' => Farmer::factory(),
            'name' => fake()->words(2, true).' Farm',
            'barangay' => 'Poblacion',
            'location_details' => fake()->streetName(),
            'area' => fake()->randomFloat(2, 0.1, 25),
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'status' => 'active',
        ];
    }
}
