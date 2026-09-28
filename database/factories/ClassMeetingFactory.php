<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassMeeting>
 */
class ClassMeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addWeek()->startOfHour();

        return [
            'school_class_id' => SchoolClass::factory(),
            'created_by' => User::factory(),
            'series_key' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'recurrence' => 'none',
            'occurrence_number' => 1,
            'occurrence_count' => 1,
            'original_starts_at' => $startsAt,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => 'scheduled',
        ];
    }
}
