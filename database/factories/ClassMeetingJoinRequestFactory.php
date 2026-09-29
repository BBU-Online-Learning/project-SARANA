<?php

namespace Database\Factories;

use App\Models\ClassMeeting;
use App\Models\ClassMeetingJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassMeetingJoinRequest>
 */
class ClassMeetingJoinRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_meeting_id' => ClassMeeting::factory(),
            'requester_user_id' => User::factory(),
            'status' => ClassMeetingJoinRequest::PENDING,
            'requested_at' => now(),
        ];
    }
}
