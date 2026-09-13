<?php

use App\Events\Chat\VoiceCallSignal;
use App\Models\CallSession;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->freezeTime();
    Event::fake();
    $this->caller = securityTestUser('student');
    $this->receiver = securityTestUser('teacher');
    $this->outsider = securityTestUser('student');
    $this->room = ChatRoom::create(['type' => 'direct', 'created_by' => $this->caller->id]);
    $this->room->members()->attach([$this->caller->id, $this->receiver->id], ['joined_at' => now()]);
    $this->callerClient = (string) Str::uuid();
    $this->receiverClient = (string) Str::uuid();
});

function startTestVideoCall(object $test): CallSession
{
    $response = $test->actingAs($test->caller)->postJson(route('chat.calls.store', $test->room), [
        'call_type' => 'video', 'client_id' => $test->callerClient, 'receiver_id' => $test->outsider->id,
    ])->assertCreated()->assertJsonPath('call.call_type', 'video');

    return CallSession::findOrFail($response->json('call.id'));
}

test('video calls derive participants from the authorized direct room and preserve old audio calls', function (): void {
    $call = startTestVideoCall($this);
    expect($call->participants()->pluck('user_id')->all())->toContain($this->caller->id, $this->receiver->id)->not->toContain($this->outsider->id);
    $this->actingAs($this->caller)->postJson(route('chat.calls.cancel', $call), ['client_id' => $this->callerClient])->assertOk();
    $this->postJson(route('chat.calls.store', $this->room))->assertCreated()->assertJsonPath('call.call_type', 'audio');
});

test('video validation and authorization reject invalid types guests outsiders and group rooms', function (): void {
    $this->postJson(route('chat.calls.store', $this->room), ['call_type' => 'video'])->assertUnauthorized();
    $this->actingAs($this->caller)->postJson(route('chat.calls.store', $this->room), ['call_type' => 'screen'])->assertUnprocessable()->assertJsonValidationErrors('call_type');
    $this->actingAs($this->outsider)->postJson(route('chat.calls.store', $this->room), ['call_type' => 'video'])->assertForbidden();
    $this->room->update(['type' => 'group']);
    $this->actingAs($this->caller)->postJson(route('chat.calls.store', $this->room), ['call_type' => 'video'])->assertForbidden();
});

test('video accept signals heartbeat and end create exactly one video history entry', function (): void {
    $call = startTestVideoCall($this);
    $this->actingAs($this->receiver)->postJson(route('chat.calls.accept', $call), ['client_id' => $this->receiverClient])
        ->assertOk()->assertJsonPath('call.status', 'active');
    $this->postJson(route('chat.calls.signal', $call), [
        'client_id' => $this->receiverClient, 'type' => 'media', 'data' => ['camera' => false, 'microphone' => true],
    ])->assertOk();
    Event::assertDispatched(VoiceCallSignal::class, fn ($event): bool => $event->signalType === 'media' && $event->targetUserId === $this->caller->id);
    $this->travel(65)->seconds();
    $this->postJson(route('chat.calls.heartbeat', $call), ['client_id' => $this->receiverClient])->assertOk();
    expect($call->participants()->where('user_id', $this->receiver->id)->first()->last_seen_at->timestamp)->toBe(now()->timestamp);
    $this->postJson(route('chat.calls.end', $call), ['client_id' => $this->receiverClient])->assertOk()->assertJsonPath('call.status', 'ended');
    $this->postJson(route('chat.calls.end', $call), ['client_id' => $this->receiverClient])->assertOk();
    expect(Message::where('call_session_id', $call->id)->count())->toBe(1)
        ->and($call->historyMessage()->first()->body)->toBe('Video call · 1 min 5 sec');
});

test('call actions and signaling cannot be taken over from another browser', function (): void {
    $call = startTestVideoCall($this);
    $this->actingAs($this->caller)->postJson(route('chat.calls.cancel', $call))->assertUnprocessable();
    $this->postJson(route('chat.calls.heartbeat', $call), ['client_id' => (string) Str::uuid()])->assertUnprocessable();
    $this->actingAs($this->receiver)->postJson(route('chat.calls.accept', $call), ['client_id' => $this->receiverClient])->assertOk();
    $this->postJson(route('chat.calls.accept', $call), ['client_id' => (string) Str::uuid()])->assertUnprocessable();
    $this->postJson(route('chat.calls.end', $call), ['client_id' => (string) Str::uuid()])->assertUnprocessable();
    expect($call->fresh()->status)->toBe('active');
});

test('outsiders cannot use any call action or inspect other current calls', function (): void {
    $call = startTestVideoCall($this);
    foreach (['accept', 'decline', 'cancel', 'end', 'timeout', 'fail', 'heartbeat', 'signal'] as $action) {
        $this->actingAs($this->outsider)->postJson(route('chat.calls.'.$action, $call), ['client_id' => $this->callerClient])->assertForbidden();
    }
    $this->getJson(route('chat.calls.current'))->assertOk()->assertJsonPath('call', null);
});

test('video decline cancel timeout and failure use the existing lifecycle', function (string $action, string $expected, bool $receiver): void {
    $call = startTestVideoCall($this);
    if ($action === 'timeout') {
        $this->travel(46)->seconds();
    }
    $this->actingAs($receiver ? $this->receiver : $this->caller)->postJson(route('chat.calls.'.$action, $call),
        ['client_id' => $receiver ? $this->receiverClient : $this->callerClient])->assertOk()->assertJsonPath('call.status', $expected);
    expect($call->historyMessage()->count())->toBe(1)
        ->and(strtolower($call->historyMessage()->first()->body))->toContain('video call');
})->with([
    ['decline', 'declined', true], ['cancel', 'cancelled', false], ['timeout', 'missed', false], ['fail', 'failed', false],
]);

test('busy participants cannot start another audio or video call in another room', function (): void {
    startTestVideoCall($this);
    $otherRoom = ChatRoom::create(['type' => 'direct', 'created_by' => $this->outsider->id]);
    $otherRoom->members()->attach([$this->outsider->id, $this->receiver->id], ['joined_at' => now()]);
    foreach (['audio', 'video'] as $type) {
        $this->actingAs($this->outsider)->postJson(route('chat.calls.store', $otherRoom), ['call_type' => $type])
            ->assertUnprocessable()->assertJsonValidationErrors('call');
    }
    expect(CallSession::count())->toBe(1);
});

test('abandoned active calls expire even when the other participant keeps sending heartbeats', function (): void {
    $call = startTestVideoCall($this);
    $this->actingAs($this->receiver)->postJson(route('chat.calls.accept', $call), ['client_id' => $this->receiverClient])->assertOk();
    $this->travel(121)->seconds();
    $this->postJson(route('chat.calls.heartbeat', $call), ['client_id' => $this->receiverClient])->assertOk();
    $this->artisan('calls:expire')->assertSuccessful();
    $call->refresh();
    expect($call->status)->toBe('failed')->and($call->end_reason)->toBe('heartbeat_timeout');
    $this->artisan('calls:expire')->assertSuccessful();
    expect($call->historyMessage()->count())->toBe(1);
});

test('heartbeats keep healthy active calls alive and reads do not claim another device call', function (): void {
    $call = startTestVideoCall($this);
    $this->actingAs($this->receiver)->postJson(route('chat.calls.accept', $call), ['client_id' => $this->receiverClient])->assertOk();
    for ($iteration = 0; $iteration < 4; $iteration++) {
        $this->travel(40)->seconds();
        foreach ([[$this->caller, $this->callerClient], [$this->receiver, $this->receiverClient]] as [$user, $client]) {
            $this->actingAs($user)->postJson(route('chat.calls.heartbeat', $call), ['client_id' => $client])->assertOk();
        }
        $this->artisan('calls:expire')->assertSuccessful();
    }
    $this->getJson(route('chat.calls.current'))->assertOk()->assertJsonPath('call.status', 'active');
    expect($call->fresh()->status)->toBe('active');
});

test('SDP types must match and terminal calls cannot signal', function (): void {
    $call = startTestVideoCall($this);
    $this->actingAs($this->caller)->postJson(route('chat.calls.signal', $call), [
        'client_id' => $this->callerClient, 'type' => 'offer', 'data' => ['description' => ['type' => 'answer', 'sdp' => "v=0\r\n"]],
    ])->assertUnprocessable();
    $this->postJson(route('chat.calls.cancel', $call), ['client_id' => $this->callerClient])->assertOk();
    $this->postJson(route('chat.calls.signal', $call), [
        'client_id' => $this->callerClient, 'type' => 'ice', 'data' => ['candidate' => ['candidate' => 'test']],
    ])->assertUnprocessable();
});

test('TURN credentials are short lived and the shared secret is never returned', function (): void {
    config(['chat.voice_calls.turn_secret' => 'server-only-secret', 'chat.voice_calls.turn_ttl_seconds' => 3600,
        'chat.voice_calls.ice_servers' => [['urls' => ['turn:relay.example.test:3478']]]]);
    $response = $this->actingAs($this->caller)->getJson(route('chat.calls.ice'))->assertOk();
    $username = (now()->timestamp + 3600).':'.$this->caller->id;
    $response->assertJsonPath('ice_servers.0.username', $username)
        ->assertJsonPath('ice_servers.0.credential', base64_encode(hash_hmac('sha1', $username, 'server-only-secret', true)))
        ->assertDontSee('server-only-secret');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('call header and overlay expose video controls without embedding TURN secrets', function (): void {
    $response = $this->actingAs($this->caller)->getJson(route('chat.rooms.show', $this->room))->assertOk();
    expect($response->json('html'))->toContain('data-call-type="audio"', 'data-call-type="video"');
    $this->get(route('chat.index'))->assertOk()->assertSee('voice-call-remote-video', false)
        ->assertSee('voice-call-local-video', false)->assertSee('js/chat/call-media.js', false)->assertSee('js/chat/call-ui.js', false);
});
