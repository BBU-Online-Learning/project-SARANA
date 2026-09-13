<?php

namespace App\Services\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageSent;
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\VoiceCallSignal;
use App\Events\Chat\VoiceCallStateChanged;
use App\Models\CallSession;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class VoiceCallService
{
    public function __construct(
        private readonly ChatAccessService $chatAccessService,
    ) {}

    public function start(User $user, ChatRoom $room, string $callType = 'audio', ?string $clientId = null): CallSession
    {
        $this->expireStaleCalls();

        $call = $this->chatAccessService->withRoom($user, $room, function (User $actor, ChatRoom $lockedRoom) use ($callType, $clientId): CallSession {
            if ($lockedRoom->type !== 'direct') {
                throw ValidationException::withMessages(['call' => 'Calls are available only in direct chats.']);
            }

            $members = $lockedRoom->members()->with('role:id,name,status')->orderBy('users.id')->get();
            if ($members->count() !== 2) {
                throw ValidationException::withMessages(['call' => 'This direct chat does not have exactly two members.']);
            }

            $recipient = $members->firstWhere('id', '!=', $actor->id);
            if (! $recipient instanceof User || ! $this->chatAccessService->ready($recipient)) {
                throw ValidationException::withMessages(['call' => 'This person is not available for calls.']);
            }

            $participantIds = [$actor->id, $recipient->id];
            User::query()->whereIn('id', $participantIds)->orderBy('id')->lockForUpdate()->get();
            $isBusy = CallSession::query()
                ->whereIn('status', CallSession::ACTIVE_STATUSES)
                ->whereHas('participants', fn ($query) => $query->whereIn('user_id', $participantIds))
                ->lockForUpdate()
                ->exists();

            if ($isBusy) {
                throw ValidationException::withMessages(['call' => 'One of you is already in another call.']);
            }

            $startedAt = now();
            $call = $lockedRoom->callSessions()->create([
                'initiated_by' => $actor->id,
                'call_type' => $callType,
                'status' => 'ringing',
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addSeconds(config('chat.voice_calls.ring_timeout_seconds', 45)),
            ]);

            $call->participants()->createMany([
                ['user_id' => $actor->id, 'joined_at' => $startedAt, 'last_seen_at' => $startedAt, 'client_id' => $clientId],
                ['user_id' => $recipient->id],
            ]);

            return $call;
        });

        $this->broadcastState($call);

        return $call;
    }

    public function current(User $user): ?CallSession
    {
        $this->expireStaleCalls($user);

        return CallSession::query()
            ->whereIn('status', CallSession::ACTIVE_STATUSES)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->with($this->relations())
            ->latest('id')
            ->first();
    }

    public function transition(User $user, CallSession $call, string $action, ?string $clientId = null): CallSession
    {
        $result = CallSession::resolveConnection()->transaction(function () use ($user, $call, $action, $clientId): array {
            $lockedCall = CallSession::query()->lockForUpdate()->findOrFail($call->id);
            $lockedCall->load($this->relations());
            $this->ensureParticipant($user, $lockedCall);
            $this->ensureClient($user, $lockedCall, $clientId);

            if (in_array($lockedCall->status, CallSession::TERMINAL_STATUSES, true)) {
                return ['call' => $lockedCall, 'changed' => false, 'message' => null];
            }

            $isCaller = $lockedCall->initiated_by === $user->id;
            $now = now();

            if ($lockedCall->status === 'ringing' && $lockedCall->expires_at?->lte($now) && $action !== 'timeout') {
                $action = 'timeout';
            }

            match ($action) {
                'accept' => $this->accept($lockedCall, $user, $isCaller, $now, $clientId),
                'decline' => $this->finishRinging($lockedCall, $isCaller, 'declined', 'declined', $now),
                'cancel' => $this->finishRinging($lockedCall, ! $isCaller, 'cancelled', 'cancelled', $now),
                'timeout' => $this->timeout($lockedCall, $now),
                'end' => $this->finishActive($lockedCall, 'ended', 'ended', $now),
                'fail' => $this->fail($lockedCall, $now),
                default => throw ValidationException::withMessages(['call' => 'The requested call action is invalid.']),
            };

            $lockedCall->refresh()->load($this->relations());
            $message = in_array($lockedCall->status, CallSession::TERMINAL_STATUSES, true)
                ? $this->createHistoryMessage($lockedCall)
                : null;

            return ['call' => $lockedCall, 'changed' => true, 'message' => $message];
        });

        if ($result['changed']) {
            $this->broadcastState($result['call']);
            if ($result['message'] instanceof Message) {
                $this->broadcastHistory($result['message']);
            }
        }

        return $result['call'];
    }

    public function signal(User $user, CallSession $call, string $type, array $data, ?string $clientId = null): void
    {
        $call->load($this->relations());
        $this->ensureParticipant($user, $call);
        $this->ensureClient($user, $call, $clientId);

        if ($call->status === 'ringing' && $call->expires_at?->isPast()) {
            $this->transition($user, $call, 'timeout', $clientId);
            throw ValidationException::withMessages(['call' => 'This call is no longer ringing.']);
        }

        $isCaller = $call->initiated_by === $user->id;
        $allowed = match ($type) {
            'offer' => in_array($call->status, CallSession::ACTIVE_STATUSES, true) && $isCaller,
            'answer' => $call->status === 'active' && ! $isCaller,
            'ice' => in_array($call->status, CallSession::ACTIVE_STATUSES, true),
            'media', 'restart' => $call->status === 'active',
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages(['call' => 'This signal is not valid for the current call state.']);
        }

        if (in_array($type, ['offer', 'answer'], true) && ($data['description']['type'] ?? null) !== $type) {
            throw ValidationException::withMessages(['data' => 'The SDP type must match the signal type.']);
        }

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > config('chat.voice_calls.signal_max_bytes', 32768)) {
            throw ValidationException::withMessages(['data' => 'The voice-call signal is too large.']);
        }

        $targetUserId = $call->participants->firstWhere('user_id', '!=', $user->id)?->user_id;
        if (! is_int($targetUserId)) {
            throw ValidationException::withMessages(['call' => 'The other call participant could not be found.']);
        }

        broadcast(new VoiceCallSignal(
            $call->id,
            $user->id,
            $targetUserId,
            $type,
            $data,
        ));
    }

    public function expireStaleCalls(?User $user = null): int
    {
        $query = CallSession::query()
            ->where(function ($query): void {
                $query->where(function ($ringing): void {
                    $ringing->where('status', 'ringing')->where('expires_at', '<=', now());
                })->orWhere(function ($active): void {
                    $cutoff = now()->subSeconds(config('chat.voice_calls.heartbeat_timeout_seconds', 120));
                    $active->where('status', 'active')->where('answered_at', '<=', $cutoff)
                        ->whereHas('participants', fn ($participants) => $participants->where(function ($stale) use ($cutoff): void {
                            $stale->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', $cutoff);
                        }));
                });
            });

        if ($user) {
            $query->whereHas('participants', fn ($participantQuery) => $participantQuery->where('user_id', $user->id));
        }

        $callIds = $query->pluck('id');
        foreach ($callIds as $callId) {
            $this->expireCall((int) $callId);
        }

        return $callIds->count();
    }

    public function payload(CallSession $call): array
    {
        $call->loadMissing($this->relations());
        $durationEndsAt = $call->ended_at ?? now();

        return [
            'id' => $call->id,
            'room_id' => $call->room_id,
            'call_type' => $call->call_type ?? 'audio',
            'initiated_by' => $call->initiated_by,
            'status' => $call->status,
            'started_at' => $call->started_at?->toISOString(),
            'answered_at' => $call->answered_at?->toISOString(),
            'expires_at' => $call->expires_at?->toISOString(),
            'ended_at' => $call->ended_at?->toISOString(),
            'end_reason' => $call->end_reason,
            'duration_seconds' => $call->answered_at ? (int) $call->answered_at->diffInSeconds($durationEndsAt) : 0,
            'participants' => $call->participants->map(fn ($participant): array => [
                'id' => $participant->user_id,
                'name' => $participant->user?->name ?? 'Unknown user',
                'avatar' => $participant->user?->profileUrl(),
                'client_id' => $participant->client_id,
            ])->values()->all(),
        ];
    }

    private function accept(CallSession $call, User $user, bool $isCaller, CarbonInterface $now, ?string $clientId): void
    {
        if ($call->status !== 'ringing' || $isCaller) {
            throw ValidationException::withMessages(['call' => 'Only the receiver can accept a ringing call.']);
        }

        $call->update(['status' => 'active', 'answered_at' => $now, 'expires_at' => null]);
        $call->participants()->where('user_id', $user->id)->update(['joined_at' => $now, 'client_id' => $clientId]);
        $call->participants()->update(['last_seen_at' => $now]);
    }

    private function finishRinging(CallSession $call, bool $wrongActor, string $status, string $reason, CarbonInterface $now): void
    {
        if ($call->status !== 'ringing' || $wrongActor) {
            throw ValidationException::withMessages(['call' => 'This ringing call cannot be changed that way.']);
        }

        $this->finish($call, $status, $reason, $now);
    }

    private function timeout(CallSession $call, CarbonInterface $now): void
    {
        if ($call->status !== 'ringing' || ($call->expires_at && $call->expires_at->gt($now->copy()->addSeconds(2)))) {
            throw ValidationException::withMessages(['call' => 'This call has not timed out.']);
        }

        $this->finish($call, 'missed', 'unanswered', $now);
    }

    private function finishActive(CallSession $call, string $status, string $reason, CarbonInterface $now): void
    {
        if ($call->status !== 'active') {
            throw ValidationException::withMessages(['call' => 'Only an active call can be ended.']);
        }

        $this->finish($call, $status, $reason, $now);
    }

    private function fail(CallSession $call, CarbonInterface $now): void
    {
        if (! in_array($call->status, CallSession::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages(['call' => 'This call is already finished.']);
        }

        $this->finish($call, 'failed', 'connection_failed', $now);
    }

    private function finish(CallSession $call, string $status, string $reason, CarbonInterface $now): void
    {
        $call->update([
            'status' => $status,
            'ended_at' => $now,
            'expires_at' => null,
            'end_reason' => $reason,
        ]);
        $call->participants()->whereNotNull('joined_at')->whereNull('left_at')->update(['left_at' => $now]);
    }

    private function expireCall(int $callId): void
    {
        $result = CallSession::resolveConnection()->transaction(function () use ($callId): array {
            $call = CallSession::query()->lockForUpdate()->find($callId);
            if (! $call) {
                return ['call' => null, 'message' => null];
            }
            $cutoff = now()->subSeconds(config('chat.voice_calls.heartbeat_timeout_seconds', 120));
            $staleActive = $call->status === 'active' && $call->answered_at?->lte($cutoff)
                && $call->participants()->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', $cutoff))->exists();
            $staleRinging = $call->status === 'ringing' && $call->expires_at?->lte(now());
            if (! $staleActive && ! $staleRinging) {
                return ['call' => null, 'message' => null];
            }
            $this->finish($call, $staleActive ? 'failed' : 'missed', $staleActive ? 'heartbeat_timeout' : 'unanswered', now());
            $call->refresh()->load($this->relations());

            return ['call' => $call, 'message' => $this->createHistoryMessage($call)];
        });

        if ($result['call'] instanceof CallSession) {
            $this->broadcastState($result['call']);
            $this->broadcastHistory($result['message']);
        }
    }

    private function ensureParticipant(User $user, CallSession $call): void
    {
        if (! $call->participants->contains('user_id', $user->id)
            || ! $this->chatAccessService->access($user, $call->room)) {
            abort(403);
        }
    }

    private function createHistoryMessage(CallSession $call): Message
    {
        $label = $call->call_type === 'video' ? 'Video call' : 'Voice call';
        $body = match ($call->status) {
            'missed' => 'Missed '.strtolower($label),
            'declined' => $label.' declined',
            'cancelled' => $label.' cancelled',
            'failed' => $label.' failed',
            default => $label.' · '.$this->formatDuration($call),
        };

        $message = Message::query()->firstOrCreate(
            ['call_session_id' => $call->id],
            [
                'room_id' => $call->room_id,
                'sender_id' => $call->initiated_by,
                'message_type' => 'call',
                'body' => $body,
            ],
        );

        if ($message->wasRecentlyCreated) {
            $call->room()->update(['last_message_at' => $message->created_at]);
        }

        return $message->load(['sender', 'room.members']);
    }

    private function formatDuration(CallSession $call): string
    {
        $seconds = $call->answered_at && $call->ended_at
            ? (int) $call->answered_at->diffInSeconds($call->ended_at)
            : 0;

        if ($seconds < 60) {
            return $seconds.' sec';
        }

        return intdiv($seconds, 60).' min '.($seconds % 60).' sec';
    }

    private function broadcastState(CallSession $call): void
    {
        $call->load($this->relations());
        $userIds = $call->participants->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        rescue(fn () => broadcast(new VoiceCallStateChanged($this->payload($call), $userIds)), report: true);
    }

    private function broadcastHistory(Message $message): void
    {
        if (! $message->wasRecentlyCreated) {
            return;
        }

        rescue(fn () => broadcast(new MessageSent($message)), report: true);
        rescue(fn () => broadcast(new ConversationUpdated($message)), report: true);

        foreach ($message->room->members as $member) {
            rescue(fn () => broadcast(new SidebarUpdated($member->id, [
                'room_id' => $message->room_id,
                'body' => $message->previewText(),
                'sender' => $message->sender->name,
                'created_at' => $message->created_at->diffForHumans(),
                'client_uuid' => null,
            ])), report: true);
        }
    }

    private function relations(): array
    {
        return ['room', 'initiator', 'participants.user'];
    }

    private function ensureClient(User $user, CallSession $call, ?string $clientId): void
    {
        $owner = $call->participants->firstWhere('user_id', $user->id)?->client_id;
        if ($owner !== null && $owner !== $clientId) {
            throw ValidationException::withMessages(['call' => 'This call is open in another tab or device.']);
        }
    }

    public function heartbeat(User $user, CallSession $call, ?string $clientId): CallSession
    {
        return CallSession::resolveConnection()->transaction(function () use ($user, $call, $clientId): CallSession {
            $call = CallSession::query()->lockForUpdate()->findOrFail($call->id);
            $call->load($this->relations());
            $this->ensureParticipant($user, $call);
            $this->ensureClient($user, $call, $clientId);
            if (in_array($call->status, CallSession::ACTIVE_STATUSES, true)) {
                $call->participants()->where('user_id', $user->id)->update(['last_seen_at' => now()]);
            }

            return $call;
        });
    }

    public function iceServers(User $user): array
    {
        $servers = config('chat.voice_calls.ice_servers', []);
        $secret = config('chat.voice_calls.turn_secret');
        if ($secret) {
            $username = now()->addSeconds(config('chat.voice_calls.turn_ttl_seconds'))->timestamp.':'.$user->id;
            foreach ($servers as &$server) {
                if (collect($server['urls'])->contains(fn ($url): bool => str_starts_with($url, 'turn:') || str_starts_with($url, 'turns:'))) {
                    $server['username'] = $username;
                    $server['credential'] = base64_encode(hash_hmac('sha1', $username, $secret, true));
                }
            }
        }

        return $servers;
    }
}
