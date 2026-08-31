<?php

namespace App\Events\Chat;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $messageId,
        public int $roomId
    ) {}

    public function broadcastOn(): array
    {
        return app(\App\Services\Chat\ChatAccessService::class)->broadcastChannels($this->roomId);
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'room_id' => $this->roomId,
        ];
    }
}
