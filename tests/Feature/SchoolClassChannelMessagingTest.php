<?php

use App\Events\Classes\SchoolClassChannelMessageSent;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

// This step tests:
// - Class members can view channel messages.
// - Class members can send messages.
// - Outsiders cannot view channels.
// - Outsiders cannot send messages.
// - Empty messages are rejected.
// - Messages use the correct sender.

uses(RefreshDatabase::class);

function createChannelMessagingTestUser(string $roleName): User
{
    $role = Role::create([
        'name' => $roleName,
        'description' => $roleName.' messaging test role',
        'status' => true,
    ]);

    return User::factory()->onboarded()->create([
        'role_id' => $role->id,
    ]);
}

function createChannelMessagingTestClass(User $owner): SchoolClass
{
    $schoolClass = SchoolClass::create([
        'name' => 'Messaging Test Class',
        'join_code' => strtoupper(fake()->unique()->bothify('MESSAGE###')),
        'description' => 'Class used for messaging tests.',
        'created_by' => $owner->id,
    ]);

    $schoolClass->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    return $schoolClass;
}

function createChannelMessagingTestChannel(
    SchoolClass $schoolClass,
    User $creator
): SchoolClassChannel {
    return SchoolClassChannel::create([
        'school_class_id' => $schoolClass->id,
        'name' => 'Discussion',
        'slug' => 'discussion',
        'created_by' => $creator->id,
        'description' => 'Discussion channel for testing.',
        'is_default' => true,
        'sort_order' => 1,
    ]);
}

test('a class member can view channel messages', function () {
    $owner = createChannelMessagingTestUser('teacher');
    $student = createChannelMessagingTestUser('student');

    $schoolClass = createChannelMessagingTestClass($owner);

    $schoolClass->members()->attach($student->id, [
        'role' => 'student',
        'joined_at' => now(),
    ]);

    $channel = createChannelMessagingTestChannel($schoolClass, $owner);

    SchoolClassChannelMessage::create([
        'school_class_channel_id' => $channel->id,
        'sender_id' => $owner->id,
        'body' => 'Welcome to the discussion.',
    ]);

    $response = $this
        ->actingAs($student)
        ->get(route('classes.channels.show', [$schoolClass, $channel]));

    $response->assertOk();
    $response->assertSee('Welcome to the discussion.');
});

test('a class member can send a channel message', function () {
    Event::fake([
        SchoolClassChannelMessageSent::class,
    ]);

    $owner = createChannelMessagingTestUser('teacher');
    $student = createChannelMessagingTestUser('student');

    $schoolClass = createChannelMessagingTestClass($owner);

    $schoolClass->members()->attach($student->id, [
        'role' => 'student',
        'joined_at' => now(),
    ]);

    $channel = createChannelMessagingTestChannel($schoolClass, $owner);

    $response = $this
        ->actingAs($student)
        ->post(route('classes.channels.messages.store', [$schoolClass, $channel]), [
            'body' => 'This is my class message.',
        ]);

    $response->assertRedirect(route('classes.channels.show', [$schoolClass, $channel]));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('school_class_channel_messages', [
        'school_class_channel_id' => $channel->id,
        'sender_id' => $student->id,
        'body' => 'This is my class message.',
    ]);
});

test('an outsider cannot view a class channel', function () {
    $owner = createChannelMessagingTestUser('teacher');
    $outsider = createChannelMessagingTestUser('student');

    $schoolClass = createChannelMessagingTestClass($owner);
    $channel = createChannelMessagingTestChannel($schoolClass, $owner);

    $response = $this
        ->actingAs($outsider)
        ->get(route('classes.channels.show', [$schoolClass, $channel]));

    $response->assertForbidden();
});

test('an outsider cannot send a class channel message', function () {
    $outsider = createChannelMessagingTestUser('student');
    $owner = createChannelMessagingTestUser('teacher');

    $schoolClass = createChannelMessagingTestClass($owner);
    $channel = createChannelMessagingTestChannel($schoolClass, $owner);

    $response = $this
        ->actingAs($outsider)
        ->post(route('classes.channels.messages.store', [$schoolClass, $channel]), [
            'body' => 'This message should be rejected.',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('school_class_channel_messages', [
        'school_class_channel_id' => $channel->id,
        'sender_id' => $outsider->id,
        'body' => 'This message should be rejected.',
    ]);
});

test('an empty channel message is rejected', function () {
    $owner = createChannelMessagingTestUser('teacher');
    $schoolClass = createChannelMessagingTestClass($owner);
    $channel = createChannelMessagingTestChannel($schoolClass, $owner);

    $response = $this
        ->actingAs($owner)
        ->from(route('classes.channels.show', [$schoolClass, $channel]))
        ->post(route('classes.channels.messages.store', [$schoolClass, $channel]), [
            'body' => '   ',
        ]);

    $response->assertRedirect(route('classes.channels.show', [$schoolClass, $channel]));
    $response->assertSessionHasErrors('body');

    $this->assertDatabaseMissing('school_class_channel_messages', [
        'school_class_channel_id' => $channel->id,
    ]);
});

test('a channel message stores the authenticated user as sender', function () {
    Event::fake([
        SchoolClassChannelMessageSent::class,
    ]);

    $owner = createChannelMessagingTestUser('teacher');
    $schoolClass = createChannelMessagingTestClass($owner);
    $channel = createChannelMessagingTestChannel($schoolClass, $owner);

    $response = $this
        ->actingAs($owner)
        ->post(route('classes.channels.messages.store', [$schoolClass, $channel]), [
            'body' => 'The sender must be the logged-in user.',
        ]);

    $response->assertRedirect(route('classes.channels.show', [$schoolClass, $channel]));
    $response->assertSessionHasNoErrors();

    $message = SchoolClassChannelMessage::query()
        ->where('school_class_channel_id', $channel->id)
        ->where('body', 'The sender must be the logged-in user.')
        ->first();

    expect($message)->not->toBeNull();
    expect($message->sender_id)->toBe($owner->id);
});
