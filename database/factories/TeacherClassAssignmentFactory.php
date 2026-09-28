<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeacherClassAssignment>
 */
class TeacherClassAssignmentFactory extends Factory
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
            'user_id' => \App\Models\User::factory(),
            'academic_year_id' => \App\Models\AcademicYear::factory(),
            'role' => 'teacher',
            'started_at' => now(),
            'source' => 'membership',
            'active_slot' => 1,
        ];
    }
}
