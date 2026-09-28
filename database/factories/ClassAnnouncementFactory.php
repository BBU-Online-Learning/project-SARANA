<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassAnnouncement>
 */
class ClassAnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_channel_id' => function (): int {
                $schoolClass = SchoolClass::factory()->create();

                return $schoolClass->channels()->create([
                    'name' => 'Announcement', 'slug' => 'announcement',
                    'is_default' => true, 'created_by' => $schoolClass->created_by,
                ])->id;
            },
            'author_id' => fn (array $attributes): int => SchoolClassChannel::query()->findOrFail($attributes['school_class_channel_id'])->created_by,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => 'draft',
        ];
    }
}
