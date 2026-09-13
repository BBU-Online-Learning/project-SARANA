<?php

use App\Events\Chat\ReadReceiptUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\ChatRoom;
use App\Models\MessageRead;
use App\Repositories\Chat\ChatRoomRepository;
use App\Services\Chat\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->sender = securityTestUser();
    $this->reader = securityTestUser();
    $this->other = securityTestUser();
    $this->room = app(GroupMembershipService::class)->create($this->sender, 'Receipts', [$this->reader->id, $this->other->id]);
    $this->message = $this->room->messages()->create(['sender_id' => $this->sender->id, 'body' => 'Read me']);
    Event::fake([ReadReceiptUpdated::class, UnreadCountUpdated::class]);
});

test('opening a room does not mark anything read', function (): void {
    $this->actingAs($this->reader)->getJson(route('chat.rooms.show', $this->room))->assertOk();
    expect(MessageRead::count())->toBe(0)
        ->and($this->room->roomMembers()->where('user_id', $this->reader->id)->value('last_read_at'))->toBeNull();
    Event::assertNotDispatched(ReadReceiptUpdated::class);
});

test('direct and group receipts are idempotent and one reader changes the timestamp status', function (string $type): void {
    $this->room->update(['type' => $type]);
    if ($type === 'direct') {
        $this->room->members()->detach($this->other);
    }
    $url = route('chat.rooms.read', $this->room);
    $payload = ['up_to_message_id' => $this->message->id];
    $this->actingAs($this->sender)->postJson($url, $payload)->assertOk()->assertJsonPath('inserted_count', 0);
    expect(MessageRead::count())->toBe(0);
    $this->getJson("/chat/messages/{$this->message->id}/html")->assertOk()->assertSee('data-read=\"0\"', false);
    $this->actingAs($this->reader)->postJson($url, $payload)->assertOk()->assertJsonPath('inserted_count', 1);
    $readAt = MessageRead::sole()->read_at;
    $this->travel(5)->minutes();
    $this->postJson($url, $payload)->assertOk()->assertJsonPath('inserted_count', 0);
    expect(MessageRead::count())->toBe(1)->and(MessageRead::sole()->read_at->equalTo($readAt))->toBeTrue();
    $this->actingAs($this->sender)->getJson(route('chat.messages.reads', $this->message))->assertOk()
        ->assertJsonPath('read_count', 1)->assertJsonPath('eligible_reader_count', $type === 'direct' ? 1 : 2)
        ->assertJsonPath('readers.0.id', $this->reader->id)->assertJsonPath('readers.0.read_at', $readAt->toISOString());
    $html = $this->getJson("/chat/messages/{$this->message->id}/html")->assertOk()->json('html');
    expect($html)->toContain('data-read="1"', '✓✓')->not->toContain('teams-read-row', 'seen-by-stack');
    $this->getJson(route('chat.rooms.reads', $this->room).'?ids='.$this->message->id)->assertOk()->assertJsonPath('statuses.0.read_count', 1);
    Event::assertDispatchedTimes(ReadReceiptUpdated::class, 1);
})->with(['direct', 'group']);

test('multiple readers are counted once each and sender is excluded even from legacy rows', function (): void {
    foreach ([$this->reader, $this->other] as $user) {
        $this->actingAs($user)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertOk();
    }
    MessageRead::create(['message_id' => $this->message->id, 'user_id' => $this->sender->id, 'read_at' => now()]);
    $this->actingAs($this->sender)->getJson(route('chat.messages.reads', $this->message))->assertOk()
        ->assertJsonPath('read_count', 2)->assertJsonPath('eligible_reader_count', 2)->assertJsonCount(2, 'readers');
});

test('read boundary excludes later messages including messages with identical timestamps', function (): void {
    $later = $this->room->messages()->create(['sender_id' => $this->sender->id, 'body' => 'Later']);
    $later->update(['created_at' => $this->message->created_at]);
    $own = $this->room->messages()->create(['sender_id' => $this->reader->id, 'body' => 'Mine']);
    $this->actingAs($this->reader)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])
        ->assertOk()->assertJsonPath('unread_count', 1);
    expect(MessageRead::where('message_id', $later->id)->exists())->toBeFalse()
        ->and(MessageRead::where('message_id', $own->id)->exists())->toBeFalse();
    expect(app(ChatRoomRepository::class)->getUserRooms($this->reader->id)->sole()->unread_count)->toBe(1);
});

test('unauthorized users and nonsenders cannot read details or spoof receipt identity', function (): void {
    $outsider = securityTestUser();
    $this->actingAs($outsider)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertForbidden();
    $this->getJson(route('chat.messages.reads', $this->message))->assertForbidden();
    $this->getJson(route('chat.rooms.reads', $this->room))->assertForbidden();
    $this->actingAs($this->reader)->getJson(route('chat.messages.reads', $this->message))->assertForbidden();
    $this->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id, 'user_id' => $this->other->id])->assertOk();
    expect(MessageRead::sole()->user_id)->toBe($this->reader->id);
});

test('missing invalid cross-room and hidden boundaries are rejected', function (): void {
    $foreign = ChatRoom::create(['type' => 'direct', 'created_by' => $this->sender->id]);
    $target = $foreign->messages()->create(['sender_id' => $this->sender->id, 'body' => 'Foreign']);
    $this->actingAs($this->reader)->postJson(route('chat.rooms.read', $this->room), [])->assertUnprocessable();
    $this->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => -1])->assertUnprocessable();
    $this->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $target->id])->assertNotFound();
    $this->message->hiddenByUsers()->create(['user_id' => $this->reader->id]);
    $this->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertNotFound();
    expect(MessageRead::count())->toBe(0);
});

test('hidden and deleted messages are excluded while historical reads remain stored', function (): void {
    $hidden = $this->room->messages()->create(['sender_id' => $this->sender->id, 'body' => 'Hidden']);
    $hidden->hiddenByUsers()->create(['user_id' => $this->reader->id]);
    $deleted = $this->room->messages()->create(['sender_id' => $this->sender->id, 'body' => 'Deleted', 'deleted_for_everyone_at' => now()]);
    $target = $this->room->messages()->create(['sender_id' => $this->sender->id, 'body' => 'Last']);
    $this->actingAs($this->reader)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $target->id])
        ->assertOk()->assertJsonPath('inserted_count', 2)->assertJsonPath('unread_count', 0);
    $this->message->hiddenByUsers()->create(['user_id' => $this->reader->id]);
    expect(MessageRead::where('message_id', $this->message->id)->exists())->toBeTrue();
    $this->actingAs($this->sender)->getJson(route('chat.messages.reads', $deleted))->assertNotFound();
    $this->message->hiddenByUsers()->create(['user_id' => $this->sender->id]);
    $this->getJson(route('chat.messages.reads', $this->message))->assertNotFound();
});

test('removed readers lose access and disappear from current reader details', function (): void {
    $this->actingAs($this->reader)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertOk();
    app(GroupMembershipService::class)->remove($this->sender, $this->room, $this->reader->id);
    $this->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertForbidden();
    $this->actingAs($this->sender)->getJson(route('chat.messages.reads', $this->message))->assertOk()
        ->assertJsonPath('read_count', 0)->assertJsonPath('eligible_reader_count', 1);
    expect(MessageRead::count())->toBe(1);
});

test('current members may explicitly read older history under existing room history rules', function (): void {
    $newReader = securityTestUser();
    $this->travel(1)->hours();
    app(GroupMembershipService::class)->add($this->sender, $this->room, [$newReader->id]);
    expect(MessageRead::count())->toBe(0);
    $this->actingAs($newReader)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertOk();
    expect(MessageRead::sole()->user_id)->toBe($newReader->id);
});

test('read event is batched and contains no reader identity and uses current channels', function (): void {
    $this->room->messages()->create(['sender_id' => $this->sender->id, 'body' => 'More']);
    $target = $this->room->messages()->latest('id')->first();
    $this->actingAs($this->reader)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $target->id])->assertOk();
    Event::assertDispatchedTimes(ReadReceiptUpdated::class, 1);
    Event::assertDispatched(ReadReceiptUpdated::class, function (ReadReceiptUpdated $event) use ($target): bool {
        expect($event->broadcastWith())->toBe(['room_id' => $this->room->id, 'up_to_message_id' => $target->id]);
        expect($event->broadcastAs())->toBe('read.updated');
        expect($event->broadcastOn())->toHaveCount(3);

        return true;
    });
});

test('legacy null receipts become reads without changing existing first-read timestamps', function (): void {
    MessageRead::create(['message_id' => $this->message->id, 'user_id' => $this->reader->id]);
    $this->actingAs($this->reader)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertOk();
    expect(MessageRead::count())->toBe(1)->and(MessageRead::sole()->read_at)->not->toBeNull();
});

test('legacy unread positions survive upgrade without fabricated exact receipts', function (): void {
    $migration = require database_path('migrations/2026_09_07_123719_preserve_legacy_chat_unread_positions.php');
    $migration->down();
    $this->room->members()->updateExistingPivot($this->reader->id, ['last_read_at' => now()]);
    $migration->up();
    expect(app(ChatRoomRepository::class)->getUserRooms($this->reader->id)->sole()->unread_count)->toBe(0)
        ->and(app(\App\Services\Chat\ReadReceiptService::class)->unreadCount($this->room, $this->reader->id))->toBe(0)
        ->and(MessageRead::count())->toBe(0);
    $this->actingAs($this->sender)->getJson(route('chat.messages.reads', $this->message))->assertJsonPath('read_count', 0);
    $this->travel(1)->seconds();
    $this->room->messages()->create(['sender_id' => $this->sender->id, 'body' => 'New unread']);
    expect(app(\App\Services\Chat\ReadReceiptService::class)->unreadCount($this->room, $this->reader->id))->toBe(1);
});

test('long unread histories are chunked and broadcast only once', function (): void {
    $rows = [];
    for ($index = 0; $index < 510; $index++) {
        $rows[] = ['room_id' => $this->room->id, 'sender_id' => $this->sender->id,
            'body' => 'History', 'created_at' => now(), 'updated_at' => now()];
    }
    \App\Models\Message::insert($rows);
    $target = $this->room->messages()->latest('id')->first();
    $this->actingAs($this->reader)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $target->id])
        ->assertOk()->assertJsonPath('inserted_count', 511);
    $this->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $target->id])
        ->assertOk()->assertJsonPath('inserted_count', 0);
    expect(MessageRead::count())->toBe(511);
    Event::assertDispatchedTimes(ReadReceiptUpdated::class, 1);
});

test('suspended readers cannot mark messages and are not exposed in reader details', function (): void {
    $this->actingAs($this->reader)->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertOk();
    $this->reader->update(['status' => 'suspended']);
    $this->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $this->message->id])->assertForbidden();
    $this->actingAs($this->sender)->getJson(route('chat.messages.reads', $this->message))->assertOk()
        ->assertJsonPath('read_count', 0)->assertJsonPath('eligible_reader_count', 1);
});
