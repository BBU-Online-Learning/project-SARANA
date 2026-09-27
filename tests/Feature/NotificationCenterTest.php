<?php

use App\Events\NotificationCenterChanged;
use App\Models\ChatRoom;
use App\Models\SchoolClass;
use App\Notifications\ActivityNotification;
use App\Services\ClassManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('notification center lists only the current users activity and tracks unread items', function (): void {
    $user = securityTestUser('student');
    $other = securityTestUser('teacher');
    $user->notify(new ActivityNotification('message', 'New message', 'Hello', route('chat.index', absolute: false)));
    $other->notify(new ActivityNotification('quiz', 'New quiz', 'Assignment', route('assessments.index', absolute: false)));

    $response = $this->actingAs($user)->getJson(route('notifications.index'))->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.title', 'New message')
        ->assertJsonPath('notifications.0.category', 'message')
        ->assertJsonCount(1, 'notifications');

    $notificationId = $response->json('notifications.0.id');
    $this->patchJson(route('notifications.read', $other->notifications()->firstOrFail()->id))->assertNotFound();
    $this->patchJson(route('notifications.read', $notificationId))->assertOk()->assertJsonPath('unread_count', 0);
    $this->getJson(route('notifications.index'))->assertJsonPath('notifications.0.read_at', $user->notifications()->firstOrFail()->read_at->toIso8601String());
});

test('the shared app header exposes the notification center', function (): void {
    $user = securityTestUser('student');

    $this->actingAs($user)->get(route('home'))->assertOk()
        ->assertSee('notification-center-toggle', false)
        ->assertSee('notification-center-panel', false)
        ->assertSee('notification-center-clear-all', false)
        ->assertSee('js/notification-center.js', false);
});

test('clearing notifications removes only the signed in users notification history', function (): void {
    $user = securityTestUser('student');
    $other = securityTestUser('teacher');
    $user->notify(new ActivityNotification('message', 'Unread', 'Hello', '/chat'));
    $user->notify(new ActivityNotification('class', 'Read', 'Class update', '/classes'));
    $user->notifications()->where('data->title', 'Read')->firstOrFail()->markAsRead();
    $other->notify(new ActivityNotification('quiz', 'Private', 'Quiz assigned', '/assessments'));
    Event::fake([NotificationCenterChanged::class]);

    $this->deleteJson(route('notifications.clear-all'))->assertUnauthorized();
    $this->actingAs($user)->deleteJson(route('notifications.clear-all'))
        ->assertOk()->assertJsonPath('unread_count', 0);

    expect($user->notifications()->count())->toBe(0)
        ->and($other->notifications()->count())->toBe(1);
    Event::assertDispatched(NotificationCenterChanged::class, fn (NotificationCenterChanged $event): bool => $event->userId === $user->id);
});

test('notification center pages through history and marks all items read', function (): void {
    $user = securityTestUser('student');
    $other = securityTestUser('teacher');
    $other->notify(new ActivityNotification('message', 'Other activity', 'Private', route('chat.index', absolute: false)));
    foreach (range(1, 25) as $index) {
        $user->notify(new ActivityNotification('class', 'Update '.$index, 'Class update', route('classes.index', absolute: false)));
    }

    $this->actingAs($user)->getJson(route('notifications.index'))
        ->assertOk()->assertJsonCount(20, 'notifications')->assertJsonPath('next_page', 2)
        ->assertJsonPath('unread_count', 25);
    $this->getJson(route('notifications.index', ['page' => 2]))
        ->assertOk()->assertJsonCount(5, 'notifications')->assertJsonPath('next_page', null);
    $this->postJson(route('notifications.read-all'))->assertOk()->assertJsonPath('unread_count', 0);
    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});

test('notification history requires authentication', function (): void {
    $this->getJson(route('notifications.index'))->assertUnauthorized();
});

test('opening a conversation reads its message and call alerts while preserving history', function (): void {
    $user = securityTestUser('student');
    $other = securityTestUser('teacher');
    $room = ChatRoom::query()->create(['type' => 'direct', 'created_by' => $other->id]);
    $room->members()->attach([$user->id, $other->id], ['joined_at' => now()]);
    $otherRoom = ChatRoom::query()->create(['type' => 'direct', 'created_by' => $other->id]);
    $otherRoom->members()->attach([$user->id, $other->id], ['joined_at' => now()]);

    $user->notify(new ActivityNotification('message', 'Message', 'Hello', '/chat', $room->id));
    $user->notify(new ActivityNotification('call', 'Missed voice call', 'From teacher', '/chat', $room->id));
    $user->notify(new ActivityNotification('call', 'Earlier missed call', 'From teacher', route('chat.index', ['room' => $room->id], false)));
    $user->notify(new ActivityNotification('message', 'Other room', 'Hello', '/chat', $otherRoom->id));
    $user->notify(new ActivityNotification('class', 'Class update', 'News', '/classes'));

    $this->actingAs($user)->postJson(route('notifications.read-room'), ['room_id' => $room->id])
        ->assertOk()->assertJsonPath('unread_count', 2);
    $notifications = $this->getJson(route('notifications.index'))->assertJsonCount(5, 'notifications')->json('notifications');
    expect(collect($notifications)->firstWhere('title', 'Missed voice call')['room_id'])->toBe($room->id);
    expect($user->notifications()->count())->toBe(5)
        ->and($user->notifications()->where('data->room_id', $room->id)->whereNull('read_at')->count())->toBe(0);

    $strangerRoom = ChatRoom::query()->create(['type' => 'direct', 'created_by' => $other->id]);
    $strangerRoom->members()->attach($other->id, ['joined_at' => now()]);
    $this->postJson(route('notifications.read-room'), ['room_id' => $strangerRoom->id])->assertForbidden();
    $this->postJson(route('notifications.read-room'), ['room_id' => 'bad'])->assertUnprocessable();
    expect($user->unreadNotifications()->count())->toBe(2);
});

test('notification center change broadcasts only to its users private channel', function (): void {
    $event = new NotificationCenterChanged(42, 7);

    expect($event->broadcastAs())->toBe('activity.notification.changed')
        ->and($event->broadcastOn()[0]->name)->toBe('private-user.42')
        ->and($event->broadcastWith())->toBe(['room_id' => 7]);
});

test('notification center refreshes unread counts when messages arrive', function (): void {
    $process = new Process(['node', base_path('tests/notification-center-client.cjs')], base_path());
    $process->mustRun();
    expect($process->getOutput())->toContain('passed');
});

test('a class owner is notified when a student joins', function (): void {
    $owner = securityTestUser('teacher');
    $student = securityTestUser('student');
    $schoolClass = SchoolClass::query()->create([
        'name' => 'Study Skills', 'join_code' => 'STUDY123', 'created_by' => $owner->id,
    ]);
    $schoolClass->members()->attach($owner, ['role' => 'owner', 'joined_at' => now()]);

    app(ClassManagementService::class)->join($student, 'STUDY123');

    expect($owner->notifications()->firstOrFail()->data['title'])->toBe('New class member')
        ->and($student->notifications()->count())->toBe(0);
});
