<?php

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Models\SchoolClassChannelRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function channelReadUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function channelReadClass(User $teacher, User $student): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);

    return $schoolClass;
}

function channelReadMessage(SchoolClassChannel $channel, User $sender, string $body): SchoolClassChannelMessage
{
    return $channel->messages()->create(['sender_id' => $sender->id, 'body' => $body]);
}

test('class channel badges count only other users visible messages and fall after reading', function (): void {
    $teacher = channelReadUser('teacher');
    $student = channelReadUser('student');
    $schoolClass = channelReadClass($teacher, $student);
    $channel = SchoolClassChannel::factory()->create(['school_class_id' => $schoolClass->id, 'created_by' => $teacher->id]);
    $first = channelReadMessage($channel, $teacher, 'First update');
    $second = channelReadMessage($channel, $teacher, 'Second update');
    channelReadMessage($channel, $student, 'My reply');
    $deleted = channelReadMessage($channel, $teacher, 'Deleted update');
    $deleted->delete();

    $this->actingAs($student)->get(route('classes.show', $schoolClass))->assertOk()->assertSee('2 unread');
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))
        ->assertOk()->assertSee('data-read-url=', false)->assertSee('2 unread');

    $this->actingAs($student)->putJson(route('classes.channels.read', [$schoolClass, $channel]), ['message_id' => $first->id])
        ->assertOk()->assertJsonPath('last_read_message_id', $first->id);
    $this->get(route('classes.show', $schoolClass))->assertOk()->assertSee('1 unread');
    $this->putJson(route('classes.channels.read', [$schoolClass, $channel]), ['message_id' => $second->id])->assertOk();
    $this->putJson(route('classes.channels.read', [$schoolClass, $channel]), ['message_id' => $first->id])
        ->assertJsonPath('last_read_message_id', $second->id);
    $this->get(route('classes.show', $schoolClass))->assertOk()->assertDontSee('unread messages');
    expect(SchoolClassChannelRead::query()->sole()->last_read_message_id)->toBe($second->id);
});

test('channel read cursors require membership and a live message in the same channel', function (): void {
    $teacher = channelReadUser('teacher');
    $student = channelReadUser('student');
    $outsider = channelReadUser('student');
    $schoolClass = channelReadClass($teacher, $student);
    $firstChannel = SchoolClassChannel::factory()->create(['school_class_id' => $schoolClass->id, 'created_by' => $teacher->id]);
    $otherChannel = SchoolClassChannel::factory()->create(['school_class_id' => $schoolClass->id, 'created_by' => $teacher->id]);
    $message = channelReadMessage($otherChannel, $teacher, 'Private to the other channel');

    $this->actingAs($outsider)->putJson(route('classes.channels.read', [$schoolClass, $firstChannel]), ['message_id' => $message->id])->assertForbidden();
    $this->actingAs($student)->putJson(route('classes.channels.read', [$schoolClass, $firstChannel]), ['message_id' => $message->id])->assertNotFound();
    $message->delete();
    $this->putJson(route('classes.channels.read', [$schoolClass, $otherChannel]), ['message_id' => $message->id])->assertNotFound();
    $this->putJson(route('classes.channels.read', [$schoolClass, $otherChannel]), ['message_id' => 0])->assertUnprocessable();
    expect(SchoolClassChannelRead::query()->count())->toBe(0);
});
