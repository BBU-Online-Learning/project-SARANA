<?php

use App\Models\ClassMeeting;
use App\Models\ClassMeetingJoinRequest;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function waitingRoomUser(string $role): User
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role], ['description' => $role, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $roleModel->id]);
}

function waitingRoomMeeting(User $teacher, array $students): array
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    foreach ($students as $student) {
        $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    }
    $startsAt = now()->addMinutes(10);
    $meeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHour(),
        'original_starts_at' => $startsAt,
    ]);

    return [$schoolClass, $meeting];
}

beforeEach(function (): void {
    config()->set('livekit.url', 'wss://example.livekit.cloud');
    config()->set('livekit.api_key', 'test-key');
    config()->set('livekit.api_secret', 'test-secret-with-sufficient-length');
});

test('a teacher admits five students before they receive meeting tokens', function (): void {
    $teacher = waitingRoomUser('teacher');
    $students = User::factory()->count(5)->onboarded()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'student'], ['description' => 'student', 'status' => true])->id,
    ]);
    [$schoolClass, $meeting] = waitingRoomMeeting($teacher, $students->all());

    $this->actingAs($teacher)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertOk();
    $references = [];
    foreach ($students as $student) {
        $this->actingAs($student)->get(route('classes.meetings.room', [$schoolClass, $meeting]))->assertOk()
            ->assertSee('data-waiting-room-url', false);
        $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
        $this->postJson(route('classes.meetings.waiting-room.store', [$schoolClass, $meeting]))
            ->assertCreated()->assertJsonPath('request.status', ClassMeetingJoinRequest::PENDING);
        $references[$student->id] = $meeting->joinRequests()->where('requester_user_id', $student->id)->value('public_uuid');
    }

    $this->actingAs($teacher)->getJson(route('classes.meetings.join-requests.index', [$schoolClass, $meeting]))
        ->assertOk()->assertJsonCount(5, 'requests');

    $identities = [];
    foreach ($students as $student) {
        $this->actingAs($teacher)->patchJson(route('classes.meetings.join-requests.update', [$schoolClass, $meeting, $references[$student->id]]), ['decision' => 'admitted'])
            ->assertOk()->assertJsonPath('request.can_enter', true);
        $response = $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertOk();
        $claims = JWT::decode($response->json('token'), new Key('test-secret-with-sufficient-length', 'HS256'));
        $identities[] = $claims->sub;
    }

    expect($identities)->toHaveCount(5)->and(array_unique($identities))->toHaveCount(5);
    $this->actingAs($teacher)->getJson(route('classes.meetings.join-requests.index', [$schoolClass, $meeting]))
        ->assertOk()->assertJsonCount(0, 'requests');
});

test('students cannot approve themselves and outsiders cannot enter a waiting room', function (): void {
    $teacher = waitingRoomUser('teacher');
    $student = waitingRoomUser('student');
    $outsider = waitingRoomUser('student');
    [$schoolClass, $meeting] = waitingRoomMeeting($teacher, [$student]);

    $this->actingAs($outsider)->postJson(route('classes.meetings.waiting-room.store', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($student)->postJson(route('classes.meetings.waiting-room.store', [$schoolClass, $meeting]))->assertCreated();
    $joinRequest = $meeting->joinRequests()->firstOrFail();
    $this->getJson(route('classes.meetings.join-requests.index', [$schoolClass, $meeting]))->assertForbidden();
    $this->patchJson(route('classes.meetings.join-requests.update', [$schoolClass, $meeting, $joinRequest]), ['decision' => 'admitted'])->assertForbidden();
    $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($teacher)->patchJson(route('classes.meetings.join-requests.update', [$schoolClass, $meeting, $joinRequest]), ['decision' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors('decision');
});

test('cancelled, denied and rescheduled admissions require a fresh decision', function (): void {
    $teacher = waitingRoomUser('teacher');
    $student = waitingRoomUser('student');
    [$schoolClass, $meeting] = waitingRoomMeeting($teacher, [$student]);
    $waitingUrl = route('classes.meetings.waiting-room.store', [$schoolClass, $meeting]);
    $decisionUrl = fn (ClassMeetingJoinRequest $joinRequest): string => route('classes.meetings.join-requests.update', [$schoolClass, $meeting, $joinRequest]);

    $this->actingAs($student)->postJson($waitingUrl)->assertCreated();
    $joinRequest = $meeting->joinRequests()->firstOrFail();
    $this->deleteJson(route('classes.meetings.waiting-room.destroy', [$schoolClass, $meeting]))
        ->assertOk()->assertJsonPath('request.status', ClassMeetingJoinRequest::CANCELLED);
    $this->postJson($waitingUrl)->assertOk()->assertJsonPath('request.status', ClassMeetingJoinRequest::PENDING);
    expect($meeting->joinRequests()->count())->toBe(1);

    $this->actingAs($teacher)->patchJson($decisionUrl($joinRequest), ['decision' => 'denied'])->assertOk();
    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $this->postJson($waitingUrl)->assertOk()->assertJsonPath('request.status', ClassMeetingJoinRequest::PENDING);
    $this->actingAs($teacher)->patchJson($decisionUrl($joinRequest), ['decision' => 'admitted'])->assertOk();
    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertOk();

    $meeting->update(['rescheduled_at' => now()->addSecond()]);
    $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $this->postJson($waitingUrl)->assertOk()->assertJsonPath('request.status', ClassMeetingJoinRequest::PENDING);
});
