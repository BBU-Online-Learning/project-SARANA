<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\User;
use Illuminate\Database\Seeder;

class SchoolClassChannelReadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || SchoolClass::query()->where('name', 'Example channel read status')->exists()) {
            return;
        }

        $teacherRole = Role::query()->firstOrCreate(['name' => Role::TEACHER], ['description' => 'Teacher', 'status' => true]);
        $studentRole = Role::query()->firstOrCreate(['name' => Role::STUDENT], ['description' => 'Student', 'status' => true]);
        $teacher = User::factory()->onboarded()->create(['role_id' => $teacherRole->id]);
        $student = User::factory()->onboarded()->create(['role_id' => $studentRole->id]);
        $schoolClass = SchoolClass::factory()->create(['name' => 'Example channel read status', 'created_by' => $teacher->id]);
        $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
        $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
        $channel = SchoolClassChannel::factory()->create(['school_class_id' => $schoolClass->id, 'created_by' => $teacher->id]);
        $first = $channel->messages()->create(['sender_id' => $teacher->id, 'body' => 'Read example']);
        $channel->messages()->create(['sender_id' => $teacher->id, 'body' => 'Unread example']);
        $channel->readStates()->create(['user_id' => $student->id, 'last_read_message_id' => $first->id, 'last_read_at' => now()]);
    }
}
