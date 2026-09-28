<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseworkAttachment>
 */
class CourseworkAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coursework_revision_id' => \App\Models\CourseworkRevision::factory(),
            'uploaded_by' => \App\Models\User::factory(),
            'path' => 'coursework/testing/'.fake()->uuid().'.txt',
            'original_name' => 'notes.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 12,
        ];
    }
}
