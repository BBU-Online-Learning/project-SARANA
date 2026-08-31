<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        if (! User::query()->whereKey($user->id)->where('status', 'active')->exists() || $attachment->trashed()) {
            return false;
        }

        $message = $attachment->message;
        $room = $message?->room;

        return $message !== null
            && $room !== null
            && (int) $attachment->room_id === (int) $room->id
            && ! $message->isDeletedForEveryone()
            && ! $message->isHiddenFor($user->id)
            && $room->members()->where('users.id', $user->id)->exists();
    }
}
