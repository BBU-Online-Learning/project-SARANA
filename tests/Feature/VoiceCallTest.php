<?php

use App\Events\Chat\VoiceCallSignal;
use App\Events\Chat\VoiceCallStateChanged;
use App\Models\CallSession;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->caller = securityTestUser('student');
    $this->receiver = securityTestUser('teacher');
    $this->outsider = securityTestUser('student');
    $this->room = ChatRoom::create([
        'type' => 'direct',
        'created_by' => $this->caller->id,
    ]);
    $this->room->members()->attach([$this->caller->id, $this->receiver->id], ['joined_at' => now()]);
});

test('a direct-chat member starts a call whose receiver is derived from room membership', function (): void {
    Event::fake([VoiceCallStateChanged::class]);

    $response = $this->actingAs($this->caller)
        ->postJson(route('chat.calls.store', $this->room))
        ->assertCreated()
        ->assertJsonPath('call.status', 'ringing')
        ->assertJsonPath('call.initiated_by', $this->caller->id);

    $call = CallSession::findOrFail($response->json('call.id'));

    expect($call->participants()->orderBy('user_id')->pluck('user_id')->all())
        ->toBe(collect([$this->caller->id, $this->receiver->id])->sort()->values()->all())
        ->and($call->expires_at)->not->toBeNull();
    Event::assertDispatched(VoiceCallStateChanged::class);
});

test('outsiders and group rooms cannot start direct voice calls', function (): void {
    $this->actingAs($this->outsider)
        ->postJson(route('chat.calls.store', $this->room))
        ->assertForbidden();

    $group = ChatRoom::create(['type' => 'group', 'name' => 'Study group', 'created_by' => $this->caller->id]);
    $group->members()->attach([$this->caller->id, $this->receiver->id], ['joined_at' => now()]);

    $this->actingAs($this->caller)
        ->postJson(route('chat.calls.store', $group))
        ->assertForbidden();
});

test('only the receiver accepts and signaling is delivered only to the other participant', function (): void {
    Event::fake([VoiceCallStateChanged::class, VoiceCallSignal::class]);
    $callId = $this->actingAs($this->caller)
        ->postJson(route('chat.calls.store', $this->room))->json('call.id');
    $call = CallSession::findOrFail($callId);

    $this->actingAs($this->caller)
        ->postJson(route('chat.calls.accept', $call))
        ->assertUnprocessable();

    $this->actingAs($this->caller)
        ->postJson(route('chat.calls.signal', $call), [
            'type' => 'offer',
            'data' => ['description' => ['type' => 'offer', 'sdp' => 'test-offer']],
        ])->assertOk();

    Event::assertDispatched(VoiceCallSignal::class, fn (VoiceCallSignal $event): bool => $event->fromUserId === $this->caller->id && $event->targetUserId === $this->receiver->id);

    $this->actingAs($this->receiver)
        ->postJson(route('chat.calls.accept', $call))
        ->assertOk()
        ->assertJsonPath('call.status', 'active');

    $this->actingAs($this->caller)
        ->postJson(route('chat.calls.signal', $call), [
            'type' => 'offer',
            'data' => ['description' => ['type' => 'offer', 'sdp' => 'late-test-offer']],
        ])->assertOk();

    $this->actingAs($this->outsider)
        ->postJson(route('chat.calls.signal', $call), [
            'type' => 'ice',
            'data' => ['candidate' => ['candidate' => 'test-candidate']],
        ])->assertForbidden();

    expect($call->fresh()->answered_at)->not->toBeNull()
        ->and($call->participants()->where('user_id', $this->receiver->id)->value('joined_at'))->not->toBeNull();
});

test('signaling preserves the exact SDP line endings for both browsers', function (): void {
    Event::fake([VoiceCallStateChanged::class, VoiceCallSignal::class]);
    $call = CallSession::findOrFail($this->actingAs($this->caller)
        ->postJson(route('chat.calls.store', $this->room))->json('call.id'));
    $sdp = "v=0\r\no=- 123 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n";

    $this->postJson(route('chat.calls.signal', $call), [
        'type' => 'offer',
        'data' => ['description' => ['type' => 'offer', 'sdp' => $sdp]],
    ])->assertOk();
    Event::assertDispatched(VoiceCallSignal::class, fn (VoiceCallSignal $event): bool => $event->signalType === 'offer' && $event->data['description']['sdp'] === $sdp);

    $this->actingAs($this->receiver)->postJson(route('chat.calls.accept', $call))->assertOk();
    $this->postJson(route('chat.calls.signal', $call), [
        'type' => 'answer',
        'data' => ['description' => ['type' => 'answer', 'sdp' => $sdp]],
    ])->assertOk();
    Event::assertDispatched(VoiceCallSignal::class, fn (VoiceCallSignal $event): bool => $event->signalType === 'answer' && $event->data['description']['sdp'] === $sdp);
});

test('ending an active call creates one call-history message', function (): void {
    $this->freezeTime();
    Event::fake();
    $call = CallSession::findOrFail($this->actingAs($this->caller)
        ->postJson(route('chat.calls.store', $this->room))->json('call.id'));

    $this->actingAs($this->receiver)->postJson(route('chat.calls.accept', $call))->assertOk();
    $this->travel(65)->seconds();
    $this->actingAs($this->caller)->postJson(route('chat.calls.end', $call))->assertOk()
        ->assertJsonPath('call.status', 'ended');
    $this->actingAs($this->caller)->postJson(route('chat.calls.end', $call))->assertOk();

    expect(Message::where('call_session_id', $call->id)->count())->toBe(1)
        ->and(Message::where('call_session_id', $call->id)->value('body'))->toBe('Voice call · 1 min 5 sec');
});

test('busy users cannot create another call and unanswered calls expire as missed', function (): void {
    Event::fake();
    $call = CallSession::findOrFail($this->actingAs($this->caller)
        ->postJson(route('chat.calls.store', $this->room))->json('call.id'));

    $this->actingAs($this->caller)
        ->postJson(route('chat.calls.store', $this->room))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('call');

    $this->travel(46)->seconds();
    $this->artisan('calls:expire')->assertSuccessful();

    expect($call->fresh()->status)->toBe('missed')
        ->and(Message::where('call_session_id', $call->id)->value('body'))->toBe('Missed voice call');
});

test('the direct chat UI exposes the call control and app-wide call manager', function (): void {
    $roomResponse = $this->actingAs($this->caller)->getJson(route('chat.rooms.show', $this->room))->assertOk();
    $this->get(route('chat.index'))
        ->assertOk()
        ->assertSee('voice-call-layer', false)
        ->assertSee('js/chat/voice-call.js', false);

    expect($roomResponse->json('html'))
        ->toContain('data-start-voice-call')
        ->toContain('Start a voice call with')
        ->and(file_get_contents(resource_path('views/layouts/app.blade.php')))
        ->toContain('chat.partials.voice-call-overlay')
        ->toContain('js/chat/voice-call.js')
        ->and(file_get_contents(public_path('js/chat/voice-call.js')))
        ->toContain('RTCPeerConnection')
        ->and(file_get_contents(public_path('js/chat/call-media.js')))
        ->toContain('getUserMedia')
        ->toContain('track.stop()');
});
