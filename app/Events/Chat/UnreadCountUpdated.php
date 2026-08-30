<?php
// app\Events\Chat\UnreadCountUpdated.php
namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnreadCountUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $roomId,
        public int $unreadCount
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'unread.count.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id'      => $this->roomId,
            'unread_count' => $this->unreadCount,
        ];
    }
}