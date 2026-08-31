<?php

namespace App\Policies\Chat;

use App\Models\ChatRoom;
use App\Models\User;
use App\Services\Chat\ChatAccessService;

class ChatRoomPolicy
{
    public function __construct(private ChatAccessService $access) {}

    public function access(User $user, ChatRoom $room): bool
    {
        return $this->access->access($user, $room);
    }

    public function manageGroup(User $user, ChatRoom $room): bool
    {
        return $this->access->manage($user, $room);
    }
}
