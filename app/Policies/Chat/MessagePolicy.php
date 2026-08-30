<?php

namespace App\Policies\Chat;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function delete(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id;
    }

    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id
         && $message->created_at->gt(now()->subMinutes(15));
    }

    public function react(User $user, Message $message): bool
    {
        return $message->room->members()
            ->where('user_id', $user->id)
            ->exists();
    }
}
