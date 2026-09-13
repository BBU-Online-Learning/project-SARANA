<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceCallSignal implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $callId,
        public int $fromUserId,
        public int $targetUserId,
        public string $signalType,
        public array $data,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->targetUserId)];
    }

    public function broadcastAs(): string
    {
        return 'voice-call.signal';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'from_user_id' => $this->fromUserId,
            'type' => $this->signalType,
            'data' => $this->data,
        ];
    }
}
