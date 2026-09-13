<?php

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageSent;
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\MessageRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake([MessageSent::class, ConversationUpdated::class, SidebarUpdated::class, UnreadCountUpdated::class]);
    $this->sender = securityTestUser('student');
    $this->recipient = securityTestUser('teacher');
    $this->room = ChatRoom::create([
        'type' => 'direct',
        'created_by' => $this->sender->id,
        'last_message_at' => now(),
    ]);
    $this->room->members()->attach([$this->sender->id, $this->recipient->id], ['joined_at' => now()]);
});

test('an authorized member can send and reload a trusted sticker message', function (string $roomType): void {
    $this->room->update(['type' => $roomType]);

    $messageId = $this->actingAs($this->sender)->postJson(route('chat.messages.store', $this->room), [
        'sticker_id' => 'star_thumbs_up',
        'client_uuid' => (string) Str::uuid(),
    ])->assertOk()->json('message_id');

    $message = Message::query()->findOrFail($messageId);
    expect($message->message_type)->toBe('sticker')
        ->and($message->sticker_id)->toBe('star_thumbs_up')
        ->and($message->body)->toBeNull()
        ->and($message->previewText())->toBe('Sticker');

    $html = $this->getJson("/chat/messages/{$message->id}/html")
        ->assertOk()->json('html');
    expect($html)->toContain('class="sticker-message"')
        ->toContain('images/stickers/star-thumbs-up.webp')
        ->toContain('Great job sticker');
})->with(['direct', 'group']);

test('sticker identifiers are allowlisted and stickers must be standalone', function (): void {
    $url = route('chat.messages.store', $this->room);
    $this->actingAs($this->sender)->postJson($url, [
        'sticker_id' => 'https://attacker.example/sticker.webp',
    ])->assertUnprocessable()->assertJsonValidationErrors('sticker_id');

    $this->postJson($url, [
        'sticker_id' => 'book_hug',
        'body' => 'Mixed content',
    ])->assertUnprocessable()->assertJsonValidationErrors('sticker_id');

    expect(Message::count())->toBe(0);
});

test('sticker retries are idempotent and a send identifier cannot change stickers', function (): void {
    $uuid = (string) Str::uuid();
    $url = route('chat.messages.store', $this->room);
    $payload = ['sticker_id' => 'pencil_celebrate', 'client_uuid' => $uuid];

    $messageId = $this->actingAs($this->sender)->postJson($url, $payload)->assertOk()->json('message_id');
    Event::fake([MessageSent::class, ConversationUpdated::class, SidebarUpdated::class, UnreadCountUpdated::class]);

    $this->postJson($url, $payload)->assertOk()->assertJsonPath('message_id', $messageId);
    $this->postJson($url, [
        'sticker_id' => 'graduation_cap',
        'client_uuid' => $uuid,
    ])->assertUnprocessable()->assertJsonValidationErrors('client_uuid');

    expect(Message::query()->where('client_uuid', $uuid)->count())->toBe(1);
    Event::assertNothingDispatched();
});

test('outsiders cannot send stickers and sticker messages cannot be edited', function (): void {
    $outsider = securityTestUser();
    $this->actingAs($outsider)->postJson(route('chat.messages.store', $this->room), [
        'sticker_id' => 'book_hug',
    ])->assertForbidden();

    $messageId = $this->actingAs($this->sender)->postJson(route('chat.messages.store', $this->room), [
        'sticker_id' => 'book_hug',
    ])->assertOk()->json('message_id');

    $this->putJson("/chat/messages/{$messageId}", ['body' => 'Turn it into text'])->assertForbidden();
});

test('stickers keep existing reactions reads and deletion behavior', function (): void {
    $messageId = $this->actingAs($this->sender)->postJson(route('chat.messages.store', $this->room), [
        'sticker_id' => 'graduation_cap',
    ])->assertOk()->json('message_id');

    $this->actingAs($this->recipient)->postJson("/chat/messages/{$messageId}/reactions", ['emoji' => '👍'])->assertOk();
    $this->postJson(route('chat.rooms.read', $this->room), ['up_to_message_id' => $messageId])
        ->assertOk()->assertJsonPath('inserted_count', 1);

    expect(MessageRead::query()->where('message_id', $messageId)->where('user_id', $this->recipient->id)->exists())->toBeTrue();

    $this->actingAs($this->sender)->deleteJson("/chat/messages/{$messageId}")->assertOk();
    expect(Message::query()->findOrFail($messageId)->deleted_for_everyone_at)->not->toBeNull();
});

test('sticker picker client behavior and bundled assets are valid', function (): void {
    foreach (config('chat.stickers') as $sticker) {
        $path = public_path($sticker['asset']);
        expect($path)->toBeFile();
        [$width, $height] = getimagesize($path);
        expect($width)->toBe(512)->and($height)->toBe(512);
    }

    $process = new Process(['node', base_path('tests/chat-sticker-picker-client.cjs')], base_path());
    $process->mustRun();

    expect($process->getOutput())->toContain('checks passed');
});
