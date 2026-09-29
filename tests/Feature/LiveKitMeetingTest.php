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

    $meetingList = $this->actingAs($student)->get(route('classes.meetings.index', $schoolClass))->assertOk();
    expect($meetingList->inertiaProps('meetings.0.group'))->toBe('ready')
        ->and($meetingList->inertiaProps('meetings.0.roomUrl'))->toBe(route('classes.meetings.room', [$schoolClass, $meeting]));
    $this->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertOk()
        ->assertSee('When we meet')->assertSee('Meeting room')->assertSee('Join meeting');
    $this->get(route('classes.meetings.room', [$schoolClass, $meeting]))
        ->assertOk()
        ->assertSee('meeting-react-root', false)
        ->assertSee('data-credentials-url', false)
        ->assertSee('resources/js/meeting-room.jsx');
    $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $this->postJson(route('classes.meetings.waiting-room.store', [$schoolClass, $meeting]))->assertCreated();
    $joinRequest = $meeting->joinRequests()->firstOrFail();
    $this->actingAs($teacher)->patchJson(route('classes.meetings.join-requests.update', [$schoolClass, $meeting, $joinRequest]), ['decision' => 'admitted'])->assertOk();
    $this->actingAs($student);
    $response = $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
    $claims = JWT::decode($response->json('token'), new Key('test-secret-with-sufficient-length', 'HS256'));
    expect($claims->sub)->toBe('user-'.$student->id)
        ->and($claims->video->room)->toBe('class-'.$schoolClass->id.'-meeting-'.$meeting->id)
        ->and($claims->video->roomJoin)->toBeTrue()
        ->and($claims->video->canPublish)->toBeTrue()
        ->and($claims->video->canSubscribe)->toBeTrue()
        ->and($claims->video->canUpdateOwnMetadata)->toBeTrue()
        ->and($claims->exp - $claims->iat)->toBeLessThanOrEqual(60)
        ->and(property_exists($claims->video, 'roomAdmin'))->toBeFalse();

    $this->actingAs($outsider)->get(route('classes.meetings.room', [$schoolClass, $meeting]))->assertForbidden();
    $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $schoolClass->memberRecords()->where('user_id', $student->id)->delete();
    $this->actingAs($student)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $meeting->update(['status' => 'cancelled']);
    $this->actingAs($teacher)->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
    $cancelledList = $this->get(route('classes.meetings.index', $schoolClass))->assertOk();
    expect($cancelledList->inertiaProps('meetings.0.group'))->toBe('earlier')
        ->and($cancelledList->inertiaProps('meetings.0.roomUrl'))->toBeNull();
});

test('six class members receive distinct credentials for the same meeting', function (): void {
    config()->set('livekit.url', 'wss://example.livekit.cloud');
    config()->set('livekit.api_key', 'test-key');
    config()->set('livekit.api_secret', 'test-secret-with-sufficient-length');

    $teacher = videoUser('teacher');
    $students = User::factory()->count(5)->onboarded()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'student'], ['description' => 'student', 'status' => true])->id,
    ]);
    $members = collect([$teacher])->concat($students);
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);

    foreach ($members as $member) {
        $schoolClass->members()->attach($member, [
            'role' => $member->is($teacher) ? 'owner' : 'student',
            'joined_at' => now(),
        ]);
    }

    $start = CarbonImmutable::now(config('app.timezone'))->addMinutes(10);
    $meeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'starts_at' => $start,
        'ends_at' => $start->addHour(),
        'original_starts_at' => $start,
    ]);
    $room = 'class-'.$schoolClass->id.'-meeting-'.$meeting->id;
    $identities = [];

    foreach ($members as $member) {
        $this->actingAs($member)->get(route('classes.meetings.room', [$schoolClass, $meeting]))->assertOk();
        if (! $member->is($teacher)) {
            $this->postJson(route('classes.meetings.waiting-room.store', [$schoolClass, $meeting]))->assertCreated();
            $joinRequest = $meeting->joinRequests()->where('requester_user_id', $member->id)->firstOrFail();
            $this->actingAs($teacher)->patchJson(route('classes.meetings.join-requests.update', [$schoolClass, $meeting, $joinRequest]), ['decision' => 'admitted'])->assertOk();
            $this->actingAs($member);
        }
        $response = $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertOk();
        $claims = JWT::decode($response->json('token'), new Key('test-secret-with-sufficient-length', 'HS256'));

        expect($response->json('url'))->toBe('wss://example.livekit.cloud')
            ->and($claims->sub)->toBe('user-'.$member->id)
            ->and($claims->name)->toBe($member->name)
            ->and($claims->video->room)->toBe($room)
            ->and($claims->video->roomJoin)->toBeTrue();

        $identities[] = $claims->sub;
    }

    expect($identities)->toHaveCount(6)
        ->and(array_unique($identities))->toHaveCount(6);
});

test('an open meeting stays on the first page when many future sessions exist', function (): void {
    config()->set('livekit.url', 'wss://example.livekit.cloud');
    config()->set('livekit.api_key', 'test-key');
    config()->set('livekit.api_secret', 'test-secret-with-sufficient-length');
    $teacher = videoUser('teacher');
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    $futureStart = CarbonImmutable::now(config('app.timezone'))->addDay();
    ClassMeeting::factory()->count(21)->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'starts_at' => $futureStart,
        'ends_at' => $futureStart->addHour(),
        'original_starts_at' => $futureStart,
    ]);
    $currentStart = CarbonImmutable::now(config('app.timezone'))->subMinutes(5);
    $currentMeeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'title' => 'Open study room',
        'starts_at' => $currentStart,
        'ends_at' => $currentStart->addHour(),
        'original_starts_at' => $currentStart,
    ]);

    $meetingList = $this->actingAs($teacher)->get(route('classes.meetings.index', $schoolClass))->assertOk();
    expect($meetingList->inertiaProps('meetings.0.group'))->toBe('ready')
        ->and($meetingList->inertiaProps('meetings.0.title'))->toBe('Open study room')
        ->and($meetingList->inertiaProps('meetings.0.roomUrl'))->toBe(route('classes.meetings.room', [$schoolClass, $currentMeeting]));
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
    $meetingList = $this->get(route('classes.meetings.index', $schoolClass))->assertOk();
    expect($meetingList->inertiaProps('meetings.0.group'))->toBe('upcoming')
        ->and($meetingList->inertiaProps('meetings.0.roomUrl'))->toBeNull();
    $this->get(route('classes.meetings.room', [$schoolClass, $meeting]))->assertStatus(503);

    config()->set('livekit.url', 'wss://example.livekit.cloud');
    config()->set('livekit.api_key', 'test-key');
    config()->set('livekit.api_secret', 'test-secret-with-sufficient-length');
    $this->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertOk()
        ->assertSee('Join opens 15 minutes before')->assertDontSee('>Join meeting<', false);
    $meetingList = $this->get(route('classes.meetings.index', $schoolClass))->assertOk();
    expect($meetingList->inertiaProps('meetings.0.group'))->toBe('upcoming')
        ->and($meetingList->inertiaProps('meetings.0.roomUrl'))->toBeNull();
    $this->post(route('classes.meetings.credentials', [$schoolClass, $meeting]))->assertForbidden();
});
