<?php

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use App\Models\ClassMeeting;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\LiveKitRoomControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function attendanceUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function attendanceMeeting(User $teacher, User ...$students): array
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    foreach ($students as $student) {
        $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    }
    $start = now()->subMinute();
    $meeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id, 'created_by' => $teacher->id,
        'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'original_starts_at' => $start,
    ]);

    return [$schoolClass, $meeting];
}

function attendanceWebhook($test, ClassMeeting $meeting, string $event, int $occurredAt, ?User $user = null, string $participantSid = 'PA_1', string $roomSid = 'RM_1', ?string $eventId = null): void
{
    $payload = [
        'id' => $eventId ?? (string) Str::uuid(),
        'event' => $event,
        'createdAt' => $occurredAt,
        'room' => ['sid' => $roomSid, 'name' => 'class-'.$meeting->school_class_id.'-meeting-'.$meeting->id],
    ];
    if ($user) {
        $payload['participant'] = ['sid' => $participantSid, 'identity' => 'user-'.$user->id];
    }
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $token = (new AccessToken(config('livekit.api_key'), config('livekit.api_secret'), (new AccessTokenOptions)->setIdentity('webhook')))
        ->setSha256(base64_encode(hash('sha256', $body, true)))->toJwt();
    $test->call('POST', route('livekit.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ], $body)->assertOk()->assertJsonPath('accepted', true);
}

beforeEach(function (): void {
    config()->set('livekit.url', 'wss://example.livekit.cloud');
    config()->set('livekit.api_key', 'test-key');
    config()->set('livekit.api_secret', 'test-secret-with-sufficient-length');
});

test('verified webhooks measure five students and a teacher across joins and leaves', function (): void {
    $teacher = attendanceUser('teacher');
    $students = collect(range(1, 5))->map(fn (): User => attendanceUser('student'));
    [$schoolClass, $meeting] = attendanceMeeting($teacher, ...$students);
    $start = now()->subMinutes(5)->timestamp;

    foreach ($students->prepend($teacher)->values() as $index => $participant) {
        attendanceWebhook($this, $meeting, 'participant_joined', $start, $participant, 'PA_'.$index);
        attendanceWebhook($this, $meeting, 'participant_left', $start + 60 + $index * 10, $participant, 'PA_'.$index);
    }

    expect($meeting->attendanceSessions()->count())->toBe(6);
    expect($meeting->attendanceSessions()->where('user_id', $students[1]->id)->first()->joined_at->timestamp)->toBe($start);
    $this->actingAs($teacher)->get(route('classes.meetings.attendance.show', [$schoolClass, $meeting]))
        ->assertOk()->assertSee('Meeting attendance')->assertSee($students[1]->name)->assertSee('00:01:10');
    $response = $this->get(route('classes.meetings.attendance.export', [$schoolClass, $meeting]))->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    ob_start();
    $response->baseResponse->sendContent();
    $csv = ob_get_clean();
    expect(substr_count($csv, "\n"))->toBe(7);
    expect($csv)->toContain($students[1]->email, ',70,1.17,No');
});

test('duplicates and out of order leave and room close events converge on actual duration', function (): void {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    [$schoolClass, $meeting] = attendanceMeeting($teacher, $student);
    $start = now()->subMinutes(5)->timestamp;

    attendanceWebhook($this, $meeting, 'participant_left', $start + 100, $student, 'PA_a', eventId: 'leave-a');
    attendanceWebhook($this, $meeting, 'participant_left', $start + 100, $student, 'PA_a', eventId: 'leave-a');
    attendanceWebhook($this, $meeting, 'participant_joined', $start, $student, 'PA_a', eventId: 'join-a');
    attendanceWebhook($this, $meeting, 'room_finished', $start + 200, roomSid: 'RM_1');
    attendanceWebhook($this, $meeting, 'participant_joined', $start + 120, $student, 'PA_b', eventId: 'join-b');
    attendanceWebhook($this, $meeting, 'participant_left', $start + 180, $student, 'PA_b', eventId: 'leave-b');

    expect($meeting->attendanceSessions()->count())->toBe(2);
    expect($meeting->attendanceSessions()->where('participant_sid', 'PA_a')->first()->left_at->timestamp)->toBe($start + 100);
    expect($meeting->attendanceSessions()->where('participant_sid', 'PA_b')->first()->left_at->timestamp)->toBe($start + 180);
    $this->actingAs($teacher)->get(route('classes.meetings.attendance.show', [$schoolClass, $meeting]))->assertSee('00:02:40');
});

test('meeting end closes open sessions and late leave corrects the recorded time', function (): void {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    [$schoolClass, $meeting] = attendanceMeeting($teacher, $student);
    $start = now()->subMinutes(3)->timestamp;
    attendanceWebhook($this, $meeting, 'participant_joined', $start, $student);
    $this->mock(LiveKitRoomControl::class)->shouldReceive('end')->once();
    $this->actingAs($teacher)->postJson(route('classes.meetings.end', [$schoolClass, $meeting]))->assertOk();
    $session = $meeting->attendanceSessions()->sole();
    expect($session->fresh()->left_at)->not->toBeNull();
    attendanceWebhook($this, $meeting, 'participant_left', $start + 100, $student);
    expect($session->fresh()->left_at->timestamp)->toBe($start + 100);
});

test('room finish before a delayed join still closes that session', function (): void {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    [$schoolClass, $meeting] = attendanceMeeting($teacher, $student);
    $start = now()->subMinutes(5)->timestamp;

    attendanceWebhook($this, $meeting, 'room_finished', $start + 90);
    attendanceWebhook($this, $meeting, 'participant_joined', $start, $student);
    $session = $meeting->attendanceSessions()->sole();
    expect($session->left_at->timestamp)->toBe($start + 90)
        ->and($session->leave_reason)->toBe('room_finished');
    $this->actingAs($teacher)->get(route('classes.meetings.attendance.show', [$schoolClass, $meeting]))->assertSee('00:01:30');
});

test('webhook authentication and teacher report authorization are enforced', function (): void {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    $outsider = attendanceUser('teacher');
    [$schoolClass, $meeting] = attendanceMeeting($teacher, $student);

    $this->postJson(route('livekit.webhook'), ['event' => 'participant_joined'])->assertUnauthorized();
    $this->withHeader('Authorization', 'Bearer invalid')->postJson(route('livekit.webhook'), ['event' => 'participant_joined'])->assertUnauthorized();
    $body = json_encode(['event' => 'participant_joined'], JSON_THROW_ON_ERROR);
    $token = (new AccessToken(config('livekit.api_key'), config('livekit.api_secret'), (new AccessTokenOptions)->setIdentity('webhook')))
        ->setSha256(base64_encode(hash('sha256', 'different body', true)))->toJwt();
    $this->call('POST', route('livekit.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ], $body)->assertUnauthorized();
    expect($meeting->attendanceSessions()->count())->toBe(0);
    $this->actingAs($student)->get(route('classes.meetings.attendance.show', [$schoolClass, $meeting]))->assertForbidden();
    $this->get(route('classes.meetings.attendance.export', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($outsider)->get(route('classes.meetings.attendance.show', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($teacher)->get(route('classes.meetings.attendance.show', [$schoolClass, $meeting]))->assertOk()->assertSee('Absent');
});

test('a verified event for a different class room cannot attach attendance to this meeting', function (): void {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    [$schoolClass, $meeting] = attendanceMeeting($teacher, $student);
    $payload = json_encode([
        'id' => (string) Str::uuid(), 'event' => 'participant_joined', 'createdAt' => now()->timestamp,
        'room' => ['sid' => 'RM_wrong', 'name' => 'class-'.($schoolClass->id + 1).'-meeting-'.$meeting->id],
        'participant' => ['sid' => 'PA_wrong', 'identity' => 'user-'.$student->id],
    ], JSON_THROW_ON_ERROR);
    $token = (new AccessToken(config('livekit.api_key'), config('livekit.api_secret'), (new AccessTokenOptions)->setIdentity('webhook')))
        ->setSha256(base64_encode(hash('sha256', $payload, true)))->toJwt();
    $this->call('POST', route('livekit.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ], $payload)->assertOk();

    expect($meeting->attendanceSessions()->count())->toBe(0);
});
