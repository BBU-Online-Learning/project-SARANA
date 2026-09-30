<?php

use App\Models\ClassMeeting;
use App\Models\ClassMeetingJoinRequest;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\LiveKitRoomControl;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function moderationUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function moderationMeeting(User $teacher, User $student): array
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    $startsAt = now()->addMinutes(10);
    $meeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHour(),
        'original_starts_at' => $startsAt,
    ]);
    $request = ClassMeetingJoinRequest::factory()->create([
        'class_meeting_id' => $meeting->id,
        'requester_user_id' => $student->id,
        'status' => ClassMeetingJoinRequest::ADMITTED,
        'decided_at' => now(),
        'decided_by' => $teacher->id,
    ]);

    return [$schoolClass, $meeting, $request];
}

beforeEach(function (): void {
    config()->set('livekit.url', 'wss://example.livekit.cloud');
    config()->set('livekit.api_key', 'test-key');
    config()->set('livekit.api_secret', 'test-secret-with-sufficient-length');
});

test('teacher removes an admitted student and prevents new meeting credentials', function (): void {
    $teacher = moderationUser('teacher');
    $student = moderationUser('student');
    [$schoolClass, $meeting, $joinRequest] = moderationMeeting($teacher, $student);
    $this->mock(LiveKitRoomControl::class)
        ->shouldReceive('removeParticipant')->once()->withArgs(fn (ClassMeeting $targetMeeting, int $userId): bool => $targetMeeting->is($meeting) && $userId === $student->id);

    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertOk();
    $this->actingAs($teacher)->postJson(route('classes.meetings.participants.remove', [$schoolClass, $meeting, $student]))
        ->assertOk()->assertJsonPath('status', 'removed');
    expect($joinRequest->fresh()->status)->toBe(ClassMeetingJoinRequest::REMOVED);
    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $this->postJson(route('classes.meetings.waiting-room.store', [$schoolClass, $meeting]))->assertForbidden();
    $this->getJson(route('classes.meetings.waiting-room.show', [$schoolClass, $meeting]))
        ->assertOk()->assertJsonPath('request.can_enter', false);
});

test('students and outsiders cannot moderate or remove a teacher', function (): void {
    $teacher = moderationUser('teacher');
    $student = moderationUser('student');
    $outsider = moderationUser('student');
    $otherTeacher = moderationUser('teacher');
    [$schoolClass, $meeting] = moderationMeeting($teacher, $student);
    $schoolClass->members()->attach($otherTeacher, ['role' => 'teacher', 'joined_at' => now()]);

    $this->actingAs($student)->postJson(route('classes.meetings.participants.remove', [$schoolClass, $meeting, $teacher]))->assertForbidden();
    $this->postJson(route('classes.meetings.end', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($outsider)->postJson(route('classes.meetings.participants.remove', [$schoolClass, $meeting, $student]))->assertForbidden();
    $this->actingAs($teacher)->postJson(route('classes.meetings.participants.remove', [$schoolClass, $meeting, $otherTeacher]))->assertForbidden();
    $this->postJson(route('classes.meetings.participants.remove', [$schoolClass, $meeting, $teacher]))->assertForbidden();
    expect($meeting->fresh()->status)->toBe('scheduled');
});

test('failed LiveKit removal keeps admission revoked and permits a retry', function (): void {
    $teacher = moderationUser('teacher');
    $student = moderationUser('student');
    [$schoolClass, $meeting, $joinRequest] = moderationMeeting($teacher, $student);
    $removeUrl = route('classes.meetings.participants.remove', [$schoolClass, $meeting, $student]);
    $this->mock(LiveKitRoomControl::class)->shouldReceive('removeParticipant')->once()
        ->andThrow(new RuntimeException('provider unavailable'));

    $this->actingAs($teacher)->postJson($removeUrl)->assertServiceUnavailable();
    expect($joinRequest->fresh()->status)->toBe(ClassMeetingJoinRequest::REMOVED);
    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();

    $this->app->forgetInstance(LiveKitRoomControl::class);
    $this->mock(LiveKitRoomControl::class)->shouldReceive('removeParticipant')->once();
    $this->actingAs($teacher)->postJson($removeUrl)->assertOk();
});

test('teacher ends a meeting for everyone and room tokens stop', function (): void {
    $teacher = moderationUser('teacher');
    $student = moderationUser('student');
    [$schoolClass, $meeting] = moderationMeeting($teacher, $student);
    $this->mock(LiveKitRoomControl::class)
        ->shouldReceive('end')->once()->withArgs(fn (ClassMeeting $targetMeeting): bool => $targetMeeting->is($meeting) && $targetMeeting->status === 'ending');

    $this->actingAs($teacher)->get(route('classes.meetings.room', [$schoolClass, $meeting]))
        ->assertOk()->assertSee('data-end-url', false)->assertSee('data-remove-url-template', false);
    $this->postJson(route('classes.meetings.end', [$schoolClass, $meeting]))
        ->assertOk()->assertJsonPath('status', 'ended');
    expect($meeting->fresh()->status)->toBe('ended')
        ->and($meeting->fresh()->ended_at)->not->toBeNull()
        ->and($meeting->fresh()->ended_by)->toBe($teacher->id);
    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($teacher)->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertSee('Ended for everyone');
});

test('failed LiveKit close blocks new tokens and lets the teacher retry', function (): void {
    $teacher = moderationUser('teacher');
    $student = moderationUser('student');
    [$schoolClass, $meeting] = moderationMeeting($teacher, $student);
    $roomControl = $this->mock(LiveKitRoomControl::class);
    $roomControl->shouldReceive('end')->once()->andThrow(new RuntimeException('provider unavailable'));

    $this->actingAs($teacher)->postJson(route('classes.meetings.end', [$schoolClass, $meeting]))->assertServiceUnavailable();
    expect($meeting->fresh()->status)->toBe('ending');
    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($teacher)->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertSee('Retry ending the meeting');

    $this->app->forgetInstance(LiveKitRoomControl::class);
    $this->mock(LiveKitRoomControl::class)->shouldReceive('end')->once();
    $this->postJson(route('classes.meetings.end', [$schoolClass, $meeting]))->assertOk();
    expect($meeting->fresh()->status)->toBe('ended');
});
