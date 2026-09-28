<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseworkSubmission>
 */
class CourseworkSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coursework_assignment_id' => \App\Models\CourseworkAssignment::factory(),
            'student_id' => \App\Models\User::factory(),
            'status' => 'draft',
            'latest_revision_number' => 0,
        ];
    }
}
