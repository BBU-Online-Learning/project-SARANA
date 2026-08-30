<?php

namespace App\Policies\Chat;

use App\Models\User;
use App\Models\ChatRoom;

class ChatRoomPolicy
{
    public function access(User $user, ChatRoom $room): bool
    {
        return $room->members()
            ->where('user_id', $user->id)
            ->exists();
    }
}