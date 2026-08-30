<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
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

    public function broadcastOn()
    {
        return new PrivateChannel(
            'user.' . $this->userId
        );
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
