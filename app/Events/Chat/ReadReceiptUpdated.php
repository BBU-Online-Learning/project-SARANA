<?php

namespace App\Events\Chat;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReadReceiptUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $roomId,
        public int $readerId,
        public string $readAt,
        public string $readerName,
        public ?int $upToMessageId = null
    ) {}

    public function broadcastOn(): array
    {
        return app(\App\Services\Chat\ChatAccessService::class)->broadcastChannels($this->roomId);
    }

    public function broadcastAs(): string
    {
        return 'read.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'up_to_message_id' => $this->upToMessageId,
        ];
    }
}
