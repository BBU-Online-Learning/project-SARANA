<?php

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageSent;
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\Attachment;
use App\Models\ChatRoom;
use App\Models\MessageUserDeletion;
use App\Services\Chat\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('chat_private');
    Storage::fake('public');
    Event::fake([MessageSent::class, ConversationUpdated::class, SidebarUpdated::class, UnreadCountUpdated::class]);

    $this->actor = securityTestUser('student');
    $this->originalSender = securityTestUser('teacher');
    $this->recipient = securityTestUser('student');
    $this->outsider = securityTestUser('student');

    $this->sourceRoom = ChatRoom::create(['type' => 'direct', 'created_by' => $this->originalSender->id]);
    $this->sourceRoom->members()->attach([$this->actor->id, $this->originalSender->id], ['joined_at' => now()]);

    $this->destinationRoom = ChatRoom::create(['type' => 'group', 'name' => 'Study group', 'created_by' => $this->actor->id]);
    $this->destinationRoom->members()->attach([$this->actor->id, $this->recipient->id], ['joined_at' => now()]);

    $this->otherDestination = ChatRoom::create(['type' => 'direct', 'created_by' => $this->actor->id]);
    $this->otherDestination->members()->attach([$this->actor->id, $this->recipient->id], ['joined_at' => now()]);

    $this->message = $this->sourceRoom->messages()->create([
        'sender_id' => $this->originalSender->id,
        'message_type' => 'text',
        'body' => 'Please review chapter four.',
    ]);
});

test('members can forward messages to one or more conversations with an optional note', function (): void {
    $second = $this->sourceRoom->messages()->create([
        'sender_id' => $this->actor->id,
        'message_type' => 'sticker',
        'sticker_id' => 'bbu_wave',
    ]);

    $response = $this->actingAs($this->actor)->postJson(route('chat.messages.forward'), [
        'message_ids' => [$this->message->id, $second->id],
        'room_ids' => [$this->destinationRoom->id, $this->otherDestination->id],
        'note' => 'For tomorrow',
    ])->assertOk()->assertJsonPath('forwarded_count', 4);

    expect($response->json('messages'))->toHaveCount(6);
    expect($response->json('sidebar_updates'))->toHaveCount(2);

    foreach ([$this->destinationRoom, $this->otherDestination] as $room) {
        expect(collect($response->json('sidebar_updates'))->firstWhere('room_id', $room->id)['message_id'])
            ->toBe($room->messages()->latest('id')->value('id'));
        Event::assertDispatched(SidebarUpdated::class, function (SidebarUpdated $event) use ($room): bool {
            return $event->userId === $this->actor->id
                && $event->payload['room_id'] === $room->id
                && $event->payload['sender_id'] === $this->actor->id;
        });
    }

    foreach ([$this->destinationRoom, $this->otherDestination] as $room) {
        $messages = $room->messages()->orderBy('id')->get();
        expect($messages)->toHaveCount(3)
            ->and($messages[0]->body)->toBe('For tomorrow')
            ->and($messages[1]->body)->toBe($this->message->body)
            ->and($messages[1]->sender_id)->toBe($this->actor->id)
            ->and($messages[1]->forwarded_from_message_id)->toBe($this->message->id)
            ->and($messages[1]->forwarded_from_sender_id)->toBe($this->originalSender->id)
            ->and($messages[1]->forwarded_from_sender_name)->toBe($this->originalSender->name)
            ->and($messages[2]->message_type)->toBe('sticker')
            ->and($messages[2]->sticker_id)->toBe('bbu_wave');
    }
});

test('forwarded messages preserve the first original sender attribution', function (): void {
    $this->actingAs($this->actor)->postJson(route('chat.messages.forward'), [
        'message_ids' => [$this->message->id],
        'room_ids' => [$this->destinationRoom->id],
    ])->assertOk();

    $firstForward = $this->destinationRoom->messages()->sole();

    $this->postJson(route('chat.messages.forward'), [
        'message_ids' => [$firstForward->id],
        'room_ids' => [$this->otherDestination->id],
    ])->assertOk();

    $secondForward = $this->otherDestination->messages()->sole();
    expect($secondForward->forwarded_from_message_id)->toBe($this->message->id)
        ->and($secondForward->forwarded_from_sender_id)->toBe($this->originalSender->id)
        ->and($secondForward->forwarded_from_sender_name)->toBe($this->originalSender->name);
});

test('forwarded attachments reuse private media and are authorized through their destination message', function (): void {
    $this->actingAs($this->originalSender);
    $attachment = app(AttachmentService::class)->store($this->message, [
        UploadedFile::fake()->image('lesson.png', 30, 30),
    ])->sole();
    $mediaPath = $attachment->getFirstMedia('attachment')->getPathRelativeToRoot();

    $this->actingAs($this->actor)->postJson(route('chat.messages.forward'), [
        'message_ids' => [$this->message->id],
        'room_ids' => [$this->destinationRoom->id],
    ])->assertOk();

    $forwardedAttachment = Attachment::query()->whereNotNull('forwarded_from_attachment_id')->sole();
    expect($forwardedAttachment->forwarded_from_attachment_id)->toBe($attachment->id)
        ->and($forwardedAttachment->media()->count())->toBe(0)
        ->and(Storage::disk('chat_private')->allFiles())->toContain($mediaPath);

    $this->actingAs($this->recipient)->get($forwardedAttachment->url())->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($this->outsider)->get($forwardedAttachment->url())->assertForbidden();
});

test('users cannot forward unavailable source messages or into conversations they cannot access', function (): void {
    $privateRoom = ChatRoom::create(['type' => 'group', 'name' => 'Private', 'created_by' => $this->outsider->id]);
    $privateRoom->members()->attach($this->outsider->id, ['joined_at' => now()]);

    $this->actingAs($this->actor)->postJson(route('chat.messages.forward'), [
        'message_ids' => [$this->message->id],
        'room_ids' => [$privateRoom->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('room_ids');

    $privateMessage = $privateRoom->messages()->create([
        'sender_id' => $this->outsider->id,
        'message_type' => 'text',
        'body' => 'Private content',
    ]);
    $this->postJson(route('chat.messages.forward'), [
        'message_ids' => [$privateMessage->id],
        'room_ids' => [$this->destinationRoom->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('message_ids');

    expect($this->destinationRoom->messages()->count())->toBe(0);
});

test('deleted hidden call and mixed-room selections cannot be forwarded', function (string $state): void {
    $message = $this->message;

    if ($state === 'deleted') {
        $message->update(['deleted_for_everyone_at' => now()]);
    } elseif ($state === 'hidden') {
        MessageUserDeletion::create(['message_id' => $message->id, 'user_id' => $this->actor->id]);
    } elseif ($state === 'call') {
        $message->update(['message_type' => 'call']);
    } else {
        $otherRoomMessage = $this->destinationRoom->messages()->create([
            'sender_id' => $this->actor->id,
            'message_type' => 'text',
            'body' => 'Different room',
        ]);
    }

    $messageIds = $state === 'mixed-room' ? [$message->id, $otherRoomMessage->id] : [$message->id];
    $this->actingAs($this->actor)->postJson(route('chat.messages.forward'), [
        'message_ids' => $messageIds,
        'room_ids' => [$this->otherDestination->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('message_ids');
})->with(['deleted', 'hidden', 'call', 'mixed-room']);

test('forwarding UI is available in chat and renders original attribution', function (): void {
    $this->actingAs($this->actor)->get(route('chat.index'))->assertOk()
        ->assertSee('id="forwardMessageModal"', false)
        ->assertSee('data-forward-url="'.route('chat.messages.forward').'"', false)
        ->assertSee('js/chat/forward.js', false)
        ->assertSee('css/chat-forward.css', false);

    $this->postJson(route('chat.messages.forward'), [
        'message_ids' => [$this->message->id],
        'room_ids' => [$this->destinationRoom->id],
    ])->assertOk();

    $forwarded = $this->destinationRoom->messages()->sole();
    $html = $this->getJson(url("/chat/messages/{$forwarded->id}/html"))->assertOk()->json('html');
    expect($html)->toContain('forward-message-btn', 'select-message-btn', 'Forwarded from', e($this->originalSender->name))
        ->toContain('ti-arrow-forward-up')
        ->not->toContain('ti ti-forward"');

    expect(file_get_contents(public_path('backend/assets/css/icons.min.css')))
        ->toContain('.ti-arrow-forward-up:before');
});
