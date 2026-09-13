<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceCallStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $call,
        public array $recipientUserIds,
    ) {}

    public function broadcastOn(): array
    {
        return collect($this->recipientUserIds)
            ->unique()
            ->map(fn (int $userId): PrivateChannel => new PrivateChannel('user.'.$userId))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'voice-call.state';
    }

    public function broadcastWith(): array
    {
        return ['call' => $this->call];
    }
}
