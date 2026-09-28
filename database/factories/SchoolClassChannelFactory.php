<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SchoolClassChannel>
 */
class SchoolClassChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'created_by' => \App\Models\User::factory(),
            'is_default' => false,
            'sort_order' => 1,
        ];
    }
}
