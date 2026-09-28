<?php

namespace Database\Factories;

use App\Models\ClassMeetingSeries;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassMeetingSeries>
 */
class ClassMeetingSeriesFactory extends Factory
{
    protected $model = ClassMeetingSeries::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'created_by' => User::factory(),
            'series_key' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'description' => null,
            'recurrence' => 'weekly',
            'weekdays' => null,
            'starts_on' => now()->addDay()->toDateString(),
            'ends_on' => null,
            'local_start_time' => '09:00:00',
            'duration_minutes' => 60,
            'timezone' => config('app.timezone'),
            'status' => 'active',
        ];
    }
}
