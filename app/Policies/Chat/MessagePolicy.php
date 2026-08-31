<?php

namespace App\Policies\Chat;

use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatAccessService;

class MessagePolicy
{
    public function delete(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id && $this->react($user, $message);
    }

    public function update(User $user, Message $message): bool
    {
        return $this->delete($user, $message) && $message->created_at->gt(now()->subMinutes(15));
    }

    public function react(User $user, Message $message): bool
    {
        return ! $message->trashed() && ! $message->isDeletedForEveryone() && $message->room
            && app(ChatAccessService::class)->access($user, $message->room);
    }
}
