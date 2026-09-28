<?php

namespace Database\Factories;

use App\Models\SchoolClassChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SchoolClassChannelRead>
 */
class SchoolClassChannelReadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_channel_id' => SchoolClassChannel::factory(),
            'user_id' => User::factory(),
            'last_read_message_id' => 0,
        ];
    }
}
