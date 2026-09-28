<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseworkRevision>
 */
class CourseworkRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coursework_submission_id' => \App\Models\CourseworkSubmission::factory(),
            'revision_number' => 1,
            'status' => 'draft',
            'draft_slot' => 1,
            'body' => fake()->paragraph(),
        ];
    }
}
