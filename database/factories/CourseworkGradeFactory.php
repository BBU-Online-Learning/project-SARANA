<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseworkGrade>
 */
class CourseworkGradeFactory extends Factory
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
            'coursework_revision_id' => fn (array $attributes): int => \App\Models\CourseworkRevision::factory()->create([
                'coursework_submission_id' => $attributes['coursework_submission_id'],
                'status' => 'submitted',
                'draft_slot' => null,
                'submitted_at' => now(),
                'is_late' => false,
            ])->id,
            'graded_by' => \App\Models\User::factory(),
            'points_awarded' => 80,
            'max_points_snapshot' => 100,
            'feedback' => fake()->sentence(),
        ];
    }
}
