<?php

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageSent;
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\Attachment;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('chat_private');
    Storage::fake('public');
    Event::fake([MessageSent::class, ConversationUpdated::class, SidebarUpdated::class, UnreadCountUpdated::class]);
    $this->sender = securityTestUser('student');
    $this->recipient = securityTestUser('teacher');
    $this->room = ChatRoom::create([
        'type' => 'direct',
        'created_by' => $this->sender->id,
        'last_message_at' => now(),
    ]);
    $this->room->members()->attach([$this->sender->id, $this->recipient->id], ['joined_at' => now()]);
    $this->actingAs($this->sender);
});

test('retrying an uncertain send returns the original message without broadcasting or storing twice', function (): void {
    $uuid = (string) Str::uuid();
    $payload = ['body' => 'Submit this assignment once.', 'client_uuid' => $uuid];

    $firstId = $this->postJson(route('chat.messages.store', $this->room), $payload)
        ->assertOk()->json('message_id');
    Event::fake([MessageSent::class, ConversationUpdated::class, SidebarUpdated::class, UnreadCountUpdated::class]);

    $this->postJson(route('chat.messages.store', $this->room), $payload)
        ->assertOk()
        ->assertJsonPath('message_id', $firstId)
        ->assertJsonPath('client_uuid', $uuid);

    expect(Message::query()->where('client_uuid', $uuid)->count())->toBe(1);
    Event::assertNothingDispatched();
});

test('new message sound metadata is sent only to the recipient', function (): void {
    $messageId = $this->postJson(route('chat.messages.store', $this->room), [
        'body' => 'A new lesson is ready.',
        'client_uuid' => (string) Str::uuid(),
    ])->assertOk()->json('message_id');

    Event::assertDispatched(SidebarUpdated::class, function (SidebarUpdated $event) use ($messageId): bool {
        return $event->userId === $this->recipient->id
            && $event->payload['message_id'] === $messageId
            && $event->payload['sender_id'] === $this->sender->id
            && $event->payload['unread_count'] === 1;
    });
    Event::assertDispatched(SidebarUpdated::class, function (SidebarUpdated $event) use ($messageId): bool {
        return $event->userId === $this->sender->id
            && $event->payload['message_id'] === $messageId
            && $event->payload['sender_id'] === $this->sender->id
            && $event->payload['unread_count'] === 0;
    });
    Event::assertDispatchedTimes(SidebarUpdated::class, 2);

    expect($this->recipient->notifications()->count())->toBe(1)
        ->and($this->recipient->notifications()->firstOrFail()->data['category'])->toBe('message')
        ->and($this->sender->notifications()->count())->toBe(0);

    expect(file_get_contents(resource_path('views/layouts/app.blade.php')))
        ->toContain('js/chat/message-sound.js');
});

test('message alerts stay silent for the visible conversation', function (): void {
    $process = new Process(['node', base_path('tests/chat-message-sound-client.cjs')], base_path());
    $process->mustRun();
    expect($process->getOutput())->toContain('checks passed');
});

test('a send identifier cannot be reused for different content room or sender', function (string $variation): void {
    $uuid = (string) Str::uuid();
    $this->postJson(route('chat.messages.store', $this->room), [
        'body' => 'Original message',
        'client_uuid' => $uuid,
    ])->assertOk();

    $payload = ['body' => 'Original message', 'client_uuid' => $uuid];
    $room = $this->room;

    if ($variation === 'content') {
        $payload['body'] = 'Different message';
    } elseif ($variation === 'room') {
        $room = ChatRoom::create(['type' => 'direct', 'created_by' => $this->sender->id]);
        $room->members()->attach([$this->sender->id, $this->recipient->id], ['joined_at' => now()]);
    } else {
        $this->actingAs($this->recipient);
    }

    $this->postJson(route('chat.messages.store', $room), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('client_uuid');

    expect(Message::query()->where('client_uuid', $uuid)->count())->toBe(1);
})->with(['content', 'room', 'sender']);

test('attachment retries reuse the stored message and reject a changed file', function (): void {
    $uuid = (string) Str::uuid();
    $image = UploadedFile::fake()->image('lesson.png', 24, 24);
    $bytes = file_get_contents($image->getPathname());
    $payload = fn (string $contents): array => [
        'body' => 'Lesson diagram',
        'client_uuid' => $uuid,
        'attachments' => [UploadedFile::fake()->createWithContent('lesson.png', $contents)],
    ];

    $messageId = $this->postJson(route('chat.messages.store', $this->room), $payload($bytes))
        ->assertOk()->json('message_id');

    $this->postJson(route('chat.messages.store', $this->room), $payload($bytes))
        ->assertOk()->assertJsonPath('message_id', $messageId);

    expect(Message::query()->where('client_uuid', $uuid)->count())->toBe(1)
        ->and(Attachment::query()->where('message_id', $messageId)->count())->toBe(1);

    $differentImage = UploadedFile::fake()->image('lesson.png', 25, 25);
    $differentBytes = file_get_contents($differentImage->getPathname());
    $this->postJson(route('chat.messages.store', $this->room), $payload($differentBytes))
        ->assertUnprocessable()->assertJsonValidationErrors('client_uuid');
});
