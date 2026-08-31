<?php

namespace App\Events\Chat;

use App\Models\ChatRoom;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class GroupTyping implements ShouldBroadcastNow
{
    public function __construct(private int $roomId, private int $userId) {}

    public function broadcastOn(): array
    {
        $room = ChatRoom::find($this->roomId);
        $user = User::find($this->userId);
        $access = app(ChatAccessService::class);

        return $user && $room?->type === 'group' && $access->access($user, $room)
            ? $access->broadcastChannels($room->id) : [];
    }

    public function broadcastAs(): string
    {
        return 'group.typing';
    }

    public function broadcastWith(): array
    {
        return ['userId' => $this->userId, 'userName' => User::find($this->userId)?->name ?? 'Member'];
    }
}
