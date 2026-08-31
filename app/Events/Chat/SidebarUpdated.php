<?php

namespace App\Events\Chat;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SidebarUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public array $payload
    ) {}

    public function broadcastOn(): array
    {
        return app(\App\Services\Chat\ChatAccessService::class)->userChannels($this->userId, (int) ($this->payload['room_id'] ?? 0));
    }

    public function broadcastAs()
    {
        return 'sidebar.updated';
    }

    public function broadcastWith()
    {
        return $this->payload;
    }
}
