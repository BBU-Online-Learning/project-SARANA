<?php

namespace Database\Factories;

use App\Models\ClassAttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassAttendanceCorrection>
 */
class ClassAttendanceCorrectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_attendance_record_id' => ClassAttendanceRecord::factory(),
            'previous_status' => 'absent',
            'new_status' => 'excused',
            'reason' => fake()->sentence(),
            'corrected_by' => User::factory(),
            'corrected_at' => now(),
        ];
    }
}
