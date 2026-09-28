<?php

use App\Models\ClassMeeting;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function videoUser(string $role): User
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role], ['description' => $role, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $roleModel->id]);
}

test('live meeting tokens are limited to current class members and the scheduled window', function (): void {
    config()->set('livekit.url', 'wss://example.livekit.cloud');
    config()->set('livekit.api_key', 'test-key');
    config()->set('livekit.api_secret', 'test-secret-with-sufficient-length');
    $teacher = videoUser('teacher');
    $student = videoUser('student');
    $outsider = videoUser('student');
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    $start = CarbonImmutable::now(config('app.timezone'))->addMinutes(10);
    $meeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'starts_at' => $start,
        'ends_at' => $start->addHour(),
        'original_starts_at' => $start,
    ]);

    $this->actingAs($student)->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertOk()->assertSee('Join meeting');
    $this->get(route('classes.meetings.room', [$schoolClass, $meeting]))
        ->assertOk()
        ->assertSee('Connect to meeting')
        ->assertSee('meeting-participant-count', false)
        ->assertSee('meeting-microphone-device', false)
        ->assertSee('meeting-camera-device', false)
        ->assertSee('Choose microphone or camera');
    $response = $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
    $claims = JWT::decode($response->json('token'), new Key('test-secret-with-sufficient-length', 'HS256'));
    expect($claims->sub)->toBe('user-'.$student->id)
        ->and($claims->video->room)->toBe('class-'.$schoolClass->id.'-meeting-'.$meeting->id)
        ->and($claims->video->roomJoin)->toBeTrue()
        ->and($claims->video->canPublish)->toBeTrue()
        ->and($claims->video->canSubscribe)->toBeTrue()
        ->and($claims->exp - $claims->iat)->toBeLessThanOrEqual(60)
        ->and(property_exists($claims->video, 'roomAdmin'))->toBeFalse();

    $this->actingAs($outsider)->get(route('classes.meetings.room', [$schoolClass, $meeting]))->assertForbidden();
    $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $schoolClass->memberRecords()->where('user_id', $student->id)->delete();
    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $meeting->update(['status' => 'cancelled']);
    $this->actingAs($teacher)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
});

test('unconfigured or early meeting does not show a broken join action', function (): void {
    $teacher = videoUser('teacher');
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    $start = CarbonImmutable::now(config('app.timezone'))->addDay();
    $meeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'starts_at' => $start,
        'ends_at' => $start->addHour(),
        'original_starts_at' => $start,
    ]);

    $this->actingAs($teacher)->get(route('classes.meetings.show', [$schoolClass, $meeting]))
        ->assertOk()->assertSee('Online joining is not available')->assertDontSee('>Join meeting<', false);
    $this->get(route('classes.meetings.room', [$schoolClass, $meeting]))->assertStatus(503);

    config()->set('livekit.url', 'wss://example.livekit.cloud');
    config()->set('livekit.api_key', 'test-key');
    config()->set('livekit.api_secret', 'test-secret-with-sufficient-length');
    $this->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertOk()
        ->assertSee('Join opens 15 minutes before')->assertDontSee('>Join meeting<', false);
    $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
});
