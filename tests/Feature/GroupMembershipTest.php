<?php

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\GroupTyping;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageReactionUpdated;
use App\Events\Chat\MessageSent;
use App\Events\Chat\MessageUpdated;
use App\Events\Chat\ReadReceiptUpdated;
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\ChatRoom;
use App\Services\Chat\AttachmentService;
use App\Services\Chat\ChatAccessService;
use App\Services\Chat\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('chat_private');
    Storage::fake('public');
    $this->owner = securityTestUser('student');
    $this->member = securityTestUser('teacher');
    $this->group = app(GroupMembershipService::class)->create($this->owner, 'Study group', [$this->member->id]);
    $this->base = route('chat.groups.show', $this->group);
    $this->actingAs($this->owner);
    $this->from($this->base);
    Event::fake([MessageSent::class, MessageUpdated::class, MessageDeleted::class, MessageReactionUpdated::class,
        ConversationUpdated::class, ReadReceiptUpdated::class, SidebarUpdated::class, UnreadCountUpdated::class, GroupTyping::class]);
});

test('every fixed role may create a group and only its creator becomes owner', function (string $role): void {
    $creator = securityTestUser($role);
    $id = $this->actingAs($creator)->postJson(route('chat.group.create'), ['name' => 'New study group', 'members' => [$this->member->id]])->assertOk()->json('room_id');
    $room = ChatRoom::findOrFail($id);
    expect($room->created_by)->toBe($creator->id)
        ->and($room->roomMembers()->where('role', 'owner')->sole()->user_id)->toBe($creator->id)
        ->and($room->roomMembers()->where('user_id', $this->member->id)->value('role'))->toBe('member');
})->with(['super_admin', 'admin', 'teacher', 'student']);

test('inactive and incomplete accounts cannot create groups', function (array $attributes, string $expected): void {
    $user = securityTestUser('student', $attributes);
    $response = $this->actingAs($user)->post(route('chat.group.create'), ['name' => 'Forbidden group']);
    if ($expected === '403') {
        $response->assertForbidden();
    } else {
        $response->assertRedirect(route($expected));
    }
    expect(ChatRoom::count())->toBe(1);
})->with([
    [['status' => 'suspended'], '403'],
    [['google2fa_enabled' => false], '2fa.setup'],
    [['must_change_password' => true], 'password.change'],
]);

test('creation rejects malformed input and ownership spoofing', function (array $data, string $field): void {
    $this->postJson(route('chat.group.create'), $data)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(ChatRoom::count())->toBe(1);
})->with([
    [['name' => ['bad']], 'name'], [['name' => '   '], 'name'],
    [['name' => 'Valid name', 'members' => 'bad'], 'members'],
    [['name' => 'Valid name', 'owner_id' => 500], 'owner_id'],
    [['name' => 'Valid name', 'created_by' => 500], 'created_by'],
    [['name' => 'Valid name', 'role' => 'admin'], 'role'],
    [['name' => 'Valid name', 'type' => 'direct'], 'type'],
]);

test('owner renames and manages membership without granting an admin tier', function (): void {
    $target = securityTestUser('super_admin');
    $this->patch($this->base, ['name' => 'Renamed group'])->assertRedirect($this->base);
    expect($this->group->fresh()->name)->toBe('Renamed group');
    $url = route('chat.groups.members.store', $this->group);
    $this->post($url, ['members' => [$target->id]])->assertRedirect($this->base);
    $this->post($url, ['members' => [$target->id]])->assertRedirect($this->base);
    expect($this->group->roomMembers()->where('user_id', $target->id)->count())->toBe(1)
        ->and($this->group->roomMembers()->where('user_id', $target->id)->value('role'))->toBe('member');
    $this->postJson($url, ['members' => [$target->id], 'role' => 'owner'])->assertUnprocessable()->assertJsonValidationErrors('role');
    $this->delete(route('chat.groups.members.destroy', [$this->group, $target]))->assertRedirect($this->base);
    expect($this->group->roomMembers()->where('user_id', $target->id)->exists())->toBeFalse();
});

test('nonowners including institution admins and legacy group admins cannot manage groups', function (string $role): void {
    $user = securityTestUser($role === 'legacy_admin' ? 'student' : $role);
    $this->group->members()->attach($user, ['role' => $role === 'legacy_admin' ? 'admin' : 'member']);
    $this->actingAs($user)->get($this->base)->assertOk()->assertDontSee('Rename group')->assertDontSee('Add active members')->assertSee('Leave group');
    $this->patchJson($this->base, ['name' => 'Forbidden'])->assertForbidden();
    $this->postJson(route('chat.groups.members.store', $this->group), ['members' => [$this->owner->id]])->assertForbidden();
    $this->deleteJson(route('chat.groups.members.destroy', [$this->group, $this->member]))->assertForbidden();
    $this->deleteJson('/chat/messages/999999')->assertNotFound();
})->with(['super_admin', 'admin', 'teacher', 'student', 'legacy_admin']);

test('outsider roles cannot access group settings or roster', function (string $role): void {
    $this->actingAs(securityTestUser($role))->get($this->base)->assertForbidden();
    $this->patchJson($this->base, ['name' => 'Forbidden'])->assertForbidden();
})->with(['super_admin', 'admin', 'teacher', 'student']);

test('owner cannot leave or be removed while members may leave without deleting history', function (): void {
    $message = $this->group->messages()->create(['sender_id' => $this->member->id, 'body' => 'Preserved history']);
    $this->postJson(route('chat.groups.leave', $this->group))->assertUnprocessable()->assertJsonValidationErrors('member');
    $this->deleteJson(route('chat.groups.members.destroy', [$this->group, $this->owner]))->assertUnprocessable();
    $this->actingAs($this->member)->post(route('chat.groups.leave', $this->group))->assertRedirect(route('chat.index'));
    expect($message->fresh()->body)->toBe('Preserved history')
        ->and($this->group->roomMembers()->where('role', 'owner')->sole()->user_id)->toBe($this->owner->id);
    $this->actingAs($this->owner)->getJson(route('chat.rooms.show', $this->group))->assertOk()->assertSee('Preserved history');
});

test('direct rooms reject every group management route', function (): void {
    $direct = ChatRoom::create(['type' => 'direct', 'created_by' => $this->owner->id]);
    $direct->members()->attach([$this->owner->id, $this->member->id]);
    $this->get(route('chat.groups.show', $direct))->assertNotFound();
    $this->patchJson(route('chat.groups.update', $direct), ['name' => 'Wrong'])->assertNotFound();
    $this->postJson(route('chat.groups.members.store', $direct), ['members' => [$this->member->id]])->assertNotFound();
    $this->deleteJson(route('chat.groups.members.destroy', [$direct, $this->member]))->assertNotFound();
    $this->postJson(route('chat.groups.leave', $direct))->assertNotFound();
    expect($direct->roomMembers()->count())->toBe(2)->and($direct->fresh()->type)->toBe('direct');
});

test('removed members lose every HTTP path and private attachments but history remains', function (): void {
    $this->actingAs($this->member);
    $message = $this->group->messages()->create(['sender_id' => $this->member->id, 'body' => 'Preserve me']);
    $attachment = app(AttachmentService::class)->store($message, [UploadedFile::fake()->image('private.png')])->sole();
    $path = $attachment->getFirstMedia('attachment')->getPathRelativeToRoot();
    app(GroupMembershipService::class)->remove($this->owner, $this->group, $this->member->id);
    $this->getJson(route('chat.rooms.show', $this->group))->assertForbidden();
    $this->getJson(route('chat.rooms.access', $this->group))->assertForbidden();
    $this->getJson("/chat/rooms/{$this->group->id}/older-messages")->assertForbidden();
    $this->getJson("/chat/rooms/{$this->group->id}/messages/search?q=Preserve")->assertForbidden();
    $this->getJson("/chat/messages/$message->id/html")->assertForbidden();
    $this->postJson(route('chat.messages.store', $this->group), ['body' => 'No'])->assertForbidden();
    $this->putJson("/chat/messages/$message->id", ['body' => 'No'])->assertForbidden();
    $this->deleteJson("/chat/messages/$message->id")->assertForbidden();
    $this->postJson("/chat/messages/$message->id/hide")->assertForbidden();
    $this->postJson(route('chat.groups.typing', $this->group))->assertForbidden();
    $this->get($attachment->url())->assertForbidden();
    $this->get($attachment->thumbUrl())->assertForbidden();
    $this->get(route('chat.attachments.download', $attachment))->assertForbidden();
    Storage::disk('chat_private')->assertExists($path);
    expect($message->fresh()->body)->toBe('Preserve me')->and($message->fresh()->deleted_for_everyone_at)->toBeNull();
});

test('direct and group messaging preserve sender permissions and attachment delivery', function (string $type): void {
    $room = $type === 'group' ? $this->group : ChatRoom::create(['type' => 'direct', 'created_by' => $this->owner->id]);
    if ($type === 'direct') {
        $room->members()->attach([$this->owner->id, $this->member->id]);
    }
    $this->postJson(route('chat.messages.store', $room), ['body' => 'Hello', 'attachments' => [UploadedFile::fake()->image('test.png')]])->assertOk();
    $message = $room->messages()->sole();
    $this->postJson(route('chat.messages.store', $room), ['body' => 'Spoof', 'sender_id' => $this->member->id])->assertUnprocessable();
    $this->actingAs($this->member)->putJson("/chat/messages/$message->id", ['body' => 'Not mine'])->assertForbidden();
    $this->deleteJson("/chat/messages/$message->id")->assertForbidden();
    $this->get($message->attachments()->sole()->url())->assertOk();
    $this->actingAs($this->owner)->putJson("/chat/messages/$message->id", ['body' => 'Edited'])->assertOk();
    $this->putJson("/chat/messages/$message->id", ['body' => ['bad']])->assertUnprocessable();
    $this->deleteJson("/chat/messages/$message->id")->assertOk();
    expect($message->fresh()->deleted_for_everyone_at)->not->toBeNull();
})->with(['direct', 'group']);

test('broadcast routing rechecks memberships and never returns retired group topics', function (): void {
    $message = $this->group->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Message']);
    $old = $this->group->roomMembers()->where('user_id', $this->member->id)->sole()->id;
    $events = [new MessageSent($message), new MessageUpdated($message), new MessageDeleted($message->id, $this->group->id),
        new ConversationUpdated($message), new ReadReceiptUpdated($this->group->id, $this->owner->id, now()->toDateTimeString(), 'Owner'),
        new MessageReactionUpdated($message->id, $this->group->id, []), new GroupTyping($this->group->id, $this->owner->id)];
    foreach ($events as $event) {
        expect(array_map(strval(...), $event->broadcastOn()))->toContain('private-chat.membership.'.$old)->not->toContain('presence-chat.room.'.$this->group->id);
    }
    app(GroupMembershipService::class)->remove($this->owner, $this->group, $this->member->id);
    foreach ($events as $event) {
        expect(array_map(strval(...), $event->broadcastOn()))->not->toContain('private-chat.membership.'.$old);
    }
    expect((new SidebarUpdated($this->member->id, ['room_id' => $this->group->id, 'body' => 'No leak']))->broadcastOn())->toBe([])
        ->and((new UnreadCountUpdated($this->member->id, $this->group->id, 3))->broadcastOn())->toBe([]);
    app(GroupMembershipService::class)->add($this->owner, $this->group, [$this->member->id]);
    $new = $this->group->roomMembers()->where('user_id', $this->member->id)->sole()->id;
    expect($new)->not->toBe($old)
        ->and(app(ChatAccessService::class)->subscription($this->member, $old))->toBeFalse()
        ->and(app(ChatAccessService::class)->subscription($this->member, $new))->toBeTrue();
});

test('inactive or incomplete targets are rejected atomically without partial membership', function (): void {
    $good = securityTestUser();
    $bad = securityTestUser('student', ['status' => 'suspended']);
    $this->postJson(route('chat.groups.members.store', $this->group), ['members' => [$good->id, $bad->id]])->assertUnprocessable();
    expect($this->group->roomMembers()->count())->toBe(2);
    $bad->update(['status' => 'active', 'must_change_password' => true]);
    $this->postJson(route('chat.group.create'), ['name' => 'Invalid group', 'members' => [$bad->id]])->assertUnprocessable();
    expect(ChatRoom::count())->toBe(1);
});

test('ownership migration uses creator membership and preserves ambiguous and legacy records', function (): void {
    $legacy = ChatRoom::create(['type' => 'group', 'created_by' => $this->owner->id, 'name' => 'Reliable creator']);
    $legacy->members()->attach([$this->owner->id, $this->member->id]);
    $unknown = ChatRoom::create(['type' => 'group', 'name' => 'Unknown creator']);
    $unknown->members()->attach($this->member, ['role' => 'admin']);
    $conflict = ChatRoom::create(['type' => 'group', 'created_by' => $this->owner->id, 'name' => 'Multiple owners']);
    $conflict->members()->attach([$this->owner->id, $this->member->id], ['role' => 'owner']);
    $before = $unknown->roomMembers()->get()->toArray();
    $migration = require database_path('migrations/2026_08_31_205320_reconcile_group_ownership_and_enable_chat_transactions.php');
    $migration->up();
    expect($legacy->roomMembers()->where('role', 'owner')->sole()->user_id)->toBe($this->owner->id)
        ->and($unknown->roomMembers()->get()->toArray())->toBe($before)
        ->and($conflict->roomMembers()->where('role', 'owner')->count())->toBe(2);
    $this->patchJson(route('chat.groups.update', $conflict), ['name' => 'Cannot guess'])->assertForbidden();
    $this->artisan('chat:audit-group-owners')->expectsOutputToContain('owner missing')->assertExitCode(1);
});

test('broadcast authorization checks current membership identity and rejects legacy group channels', function (): void {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
        'driver' => 'reverb', 'key' => 'isolated-key', 'secret' => 'isolated-secret', 'app_id' => 'isolated-test',
    ]]);
    \Illuminate\Support\Facades\Broadcast::purge('reverb');
    require base_path('routes/channels.php');
    $id = $this->group->roomMembers()->where('user_id', $this->member->id)->sole()->id;
    $payload = ['socket_id' => '1.1', 'channel_name' => 'private-chat.membership.'.$id];
    $this->actingAs($this->member)->postJson('/broadcasting/auth', $payload)->assertOk()->assertJsonStructure(['auth']);
    $this->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'presence-chat.room.'.$this->group->id])->assertForbidden();
    $this->actingAs($this->owner)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $this->actingAs(securityTestUser('super_admin'))->postJson('/broadcasting/auth', $payload)->assertForbidden();
    app(GroupMembershipService::class)->remove($this->owner, $this->group, $this->member->id);
    $this->actingAs($this->member)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    app(GroupMembershipService::class)->add($this->owner, $this->group, [$this->member->id]);
    $this->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $new = $this->group->roomMembers()->where('user_id', $this->member->id)->sole()->id;
    $this->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-chat.membership.'.$new])->assertOk();
    $direct = ChatRoom::create(['type' => 'direct', 'created_by' => $this->owner->id]);
    $direct->members()->attach([$this->owner->id, $this->member->id]);
    $this->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'presence-chat.room.'.$direct->id])->assertOk();
});

test('removing a member through another group cannot remove their actual membership', function (): void {
    $other = app(GroupMembershipService::class)->create($this->owner, 'Other group', []);
    $this->deleteJson(route('chat.groups.members.destroy', [$other, $this->member]))->assertNotFound();
    expect($this->group->roomMembers()->where('user_id', $this->member->id)->exists())->toBeTrue();
});
