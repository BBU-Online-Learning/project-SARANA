<?php

use App\Events\Classes\SchoolClassChannelMessageSent;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannelMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = securityTestUser('teacher');
    $this->student = securityTestUser();
    $this->schoolClass = SchoolClass::create(['name' => 'Messaging', 'created_by' => $this->owner->id, 'join_code' => Str::random(8)]);
    $this->schoolClass->members()->attach($this->owner, ['role' => 'owner']);
    $this->schoolClass->members()->attach($this->student, ['role' => 'student']);
    $this->channel = $this->schoolClass->channels()->create(['name' => 'General', 'slug' => 'general', 'created_by' => $this->owner->id]);
    $this->url = route('classes.channels.messages.index', [$this->schoolClass, $this->channel]);
    $this->actingAs($this->student);
    Event::fake([SchoolClassChannelMessageSent::class]);
});

test('current senders edit and soft delete their messages with synchronization tombstones', function (): void {
    $response = $this->postJson($this->url, ['body' => 'Before', 'client_uuid' => (string) Str::uuid()])->assertCreated();
    $id = $response->json('message.message_id');
    $this->patchJson("$this->url/$id", ['body' => 'After'])->assertOk()
        ->assertJsonPath('message.body', 'After')->assertJsonPath('message.is_edited', true);
    expect(SchoolClassChannelMessage::findOrFail($id)->edited_at)->not->toBeNull();
    $this->actingAs($this->owner)->getJson($this->url.'?after_id='.$id.'&visible_ids[]='.$id)
        ->assertJsonPath('updates.0.body', 'After')->assertJsonPath('updates.0.can_modify', false);
    $this->actingAs($this->student)->deleteJson("$this->url/$id")->assertOk()->assertJsonPath('message.deleted', true)
        ->assertJsonPath('message.body', null);
    $this->deleteJson("$this->url/$id")->assertOk();
    $this->assertSoftDeleted('school_class_channel_messages', ['id' => $id]);
    $this->getJson($this->url)->assertJsonCount(0, 'messages')->assertDontSee('After');
    $this->getJson($this->url.'?after_id='.$id.'&visible_ids[]='.$id)
        ->assertJsonPath('updates.0.deleted', true)->assertJsonPath('updates.0.body', null);
    $this->patchJson("$this->url/$id", ['body' => 'Revive'])->assertNotFound();
    Event::assertDispatchedTimes(SchoolClassChannelMessageSent::class, 4);
});

test('members and administrators never edit or delete another sender message', function (string $role, string $method): void {
    $message = $this->channel->messages()->create(['sender_id' => $this->student->id, 'body' => 'Private authorship']);
    $actor = $role === 'owner' ? $this->owner : securityTestUser($role);
    if ($role !== 'owner') {
        $this->schoolClass->members()->attach($actor, ['role' => 'student']);
    }
    $this->actingAs($actor)->json($method, "$this->url/$message->id", ['body' => 'Tampered'])->assertForbidden();
    expect($message->fresh()->body)->toBe('Private authorship')->and($message->fresh()->trashed())->toBeFalse();
})->with(['owner', 'teacher', 'student', 'admin', 'super_admin'])->with(['PATCH', 'DELETE']);

test('removed and archived members cannot mutate their own messages', function (string $state, string $method): void {
    $message = $this->channel->messages()->create(['sender_id' => $this->student->id, 'body' => 'Existing']);
    if ($state === 'removed') {
        $this->schoolClass->members()->detach($this->student);
    } else {
        $this->schoolClass->forceFill(['archived_at' => now()])->save();
    }
    $this->json($method, "$this->url/$message->id", ['body' => 'Tampered'])->assertForbidden();
    expect($message->fresh()->body)->toBe('Existing')->and($message->fresh()->trashed())->toBeFalse();
})->with(['removed', 'archived'])->with(['PATCH', 'DELETE']);

test('cross channel and cross class message routes are rejected', function (string $method): void {
    $other = $this->schoolClass->channels()->create(['name' => 'Other', 'slug' => 'other', 'created_by' => $this->owner->id]);
    $message = $other->messages()->create(['sender_id' => $this->student->id, 'body' => 'Other']);
    $this->json($method, "$this->url/$message->id", ['body' => 'Tampered'])->assertNotFound();
    $another = SchoolClass::create(['name' => 'Unrelated', 'created_by' => $this->owner->id, 'join_code' => Str::random(8)]);
    $url = route('classes.channels.messages.'.($method === 'PATCH' ? 'update' : 'destroy'), [$another, $other, $message]);
    $this->json($method, $url, ['body' => 'Tampered'])->assertNotFound();
    $this->getJson($this->url.'?visible_ids[]='.$message->id)->assertJsonCount(0, 'updates');
})->with(['PATCH', 'DELETE']);

test('malformed empty and overlong bodies fail sending and editing without server errors', function (mixed $body): void {
    $message = $this->channel->messages()->create(['sender_id' => $this->student->id, 'body' => 'Keep']);
    $this->postJson($this->url, ['body' => $body])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->patchJson("$this->url/$message->id", ['body' => $body])->assertUnprocessable()->assertJsonValidationErrors('body');
    expect($message->fresh()->body)->toBe('Keep');
})->with([null, '', '  ', '<b> </b>', [['array']], 23, true, str_repeat('x', 5001)]);

test('spoofed sender channel and edit metadata are rejected', function (string $field): void {
    $message = $this->channel->messages()->create(['sender_id' => $this->student->id, 'body' => 'Keep']);
    $payload = ['body' => 'Forged', $field => 999];
    $this->postJson($this->url, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->patchJson("$this->url/$message->id", $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['sender_id', 'school_class_channel_id', 'school_class_id', 'is_edited', 'edited_at', 'deleted_at']);

test('repeated sends return the same message and never overwrite reuse or revive deletion', function (): void {
    $uuid = (string) Str::uuid();
    $id = $this->postJson($this->url, ['body' => 'Once', 'client_uuid' => $uuid])->assertCreated()->json('message.message_id');
    $this->postJson($this->url, ['body' => 'Once', 'client_uuid' => $uuid])->assertOk()->assertJsonPath('message.message_id', $id);
    $this->postJson($this->url, ['body' => 'Changed', 'client_uuid' => $uuid])->assertUnprocessable()->assertJsonValidationErrors('client_uuid');
    $this->actingAs($this->owner)->postJson($this->url, ['body' => 'Once', 'client_uuid' => $uuid])->assertUnprocessable();
    $other = $this->schoolClass->channels()->create(['name' => 'Other', 'slug' => 'other', 'created_by' => $this->owner->id]);
    $this->actingAs($this->student)->postJson(route('classes.channels.messages.store', [$this->schoolClass, $other]), ['body' => 'Once', 'client_uuid' => $uuid])->assertUnprocessable();
    expect(SchoolClassChannelMessage::count())->toBe(1);
    Event::assertDispatchedTimes(SchoolClassChannelMessageSent::class, 1);
    $this->deleteJson("$this->url/$id")->assertOk();
    $this->postJson($this->url, ['body' => 'Once', 'client_uuid' => $uuid])->assertUnprocessable();
    expect(SchoolClassChannelMessage::count())->toBe(0)->and(SchoolClassChannelMessage::withTrashed()->count())->toBe(1);
});

test('history is bounded and stable despite equal timestamps and concurrent inserts or deletions', function (): void {
    for ($i = 1; $i <= 125; $i++) {
        $this->channel->messages()->create(['sender_id' => $this->student->id, 'body' => 'History item '.$i]);
    }
    $this->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))
        ->assertOk()->assertViewHas('messages', fn ($messages) => $messages->count() === 50 && $messages->first()->id === 76);
    $latest = $this->getJson($this->url)->assertJsonCount(50, 'messages')->assertJsonPath('has_more', true);
    $before = $latest->json('before_id');
    $this->channel->messages()->create(['sender_id' => $this->student->id, 'body' => 'Concurrent insert']);
    SchoolClassChannelMessage::findOrFail(20)->delete();
    $older = $this->getJson($this->url.'?before_id='.$before)->assertJsonCount(50, 'messages');
    expect(array_intersect($latest->json('messages.*.message_id'), $older->json('messages.*.message_id')))->toBe([]);
    $last = $this->getJson($this->url.'?before_id='.$older->json('before_id'))->assertJsonCount(24, 'messages')->assertJsonPath('has_more', false);
    $ids = array_merge($last->json('messages.*.message_id'), $older->json('messages.*.message_id'), $latest->json('messages.*.message_id'));
    expect($ids)->toBe(array_values(array_diff(range(1, 125), [20])));
    $this->getJson($this->url.'?after_id=125')->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Concurrent insert');
});

test('cursor and synchronization payloads are validated and bounded', function (array $query): void {
    $this->getJson($this->url.'?'.http_build_query($query))->assertUnprocessable();
})->with([
    [['before_id' => -1]], [['before_id' => ['bad']]], [['after_id' => 1, 'before_id' => 3]],
    [['visible_ids' => range(1, 201)]], [['visible_ids' => ['bad']]], [['sync_only' => ['bad']]],
]);

test('deleted senders render safely and escaped text never becomes executable markup', function (): void {
    $this->channel->messages()->create(['sender_id' => $this->student->id, 'body' => '<script>alert("x")</script>']);
    $this->student->delete();
    $this->actingAs($this->owner)->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))
        ->assertOk()->assertSee('Deleted user')->assertDontSee('<script>alert("x")</script>', false);
    $this->getJson($this->url)->assertJsonPath('messages.0.sender_name', 'Deleted user');
});

test('ordinary form validation preserves malformed drafts without crashing the page', function (): void {
    $url = route('classes.channels.show', [$this->schoolClass, $this->channel]);
    $this->from($url)->post($this->url, ['body' => ['bad']])->assertRedirect($url)->assertSessionHasErrors('body');
    $this->get($url)->assertOk()->assertSee('Message text must be a string.');
});

test('two independent class clients synchronize edits deletions retries and reconnects', function (): void {
    $process = new \Symfony\Component\Process\Process(['node', base_path('tests/class-channel-client.cjs'), public_path('js/classes/channel-realtime.js'), 'sync']);
    $process->mustRun();
    expect($process->isSuccessful())->toBeTrue();
});
