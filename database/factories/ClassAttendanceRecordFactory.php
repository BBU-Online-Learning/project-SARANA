<?php

namespace Database\Factories;

use App\Models\ClassAttendanceRegister;
use App\Models\StudentClassEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassAttendanceRecord>
 */
class ClassAttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_attendance_register_id' => ClassAttendanceRegister::factory(),
            'student_id' => User::factory(),
            'student_class_enrollment_id' => StudentClassEnrollment::factory(),
            'student_name_snapshot' => fake()->name(),
            'status' => null,
        ];
    }
}
