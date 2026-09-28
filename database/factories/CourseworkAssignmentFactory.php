<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseworkAssignment>
 */
class CourseworkAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => \App\Models\SchoolClass::factory(),
            'created_by' => \App\Models\User::factory(),
            'academic_year_id' => null,
            'subject_id' => null,
            'title' => fake()->sentence(4),
            'instructions' => fake()->paragraph(),
            'max_points' => 100,
            'due_at' => now()->addWeek(),
            'allow_resubmissions' => true,
            'status' => 'draft',
        ];
    }
}
