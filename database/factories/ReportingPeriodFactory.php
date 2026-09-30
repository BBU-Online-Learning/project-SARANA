<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\ReportingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportingPeriod>
 */
class ReportingPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('TERM-##??'),
            'sequence' => 1,
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'status' => 'draft',
        ];
    }
}
