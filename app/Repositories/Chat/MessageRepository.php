<?php

namespace App\Repositories\Chat;

use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class MessageRepository
{
    /*
    |--------------------------------------------------------------------------
    | STORE MESSAGE
    |--------------------------------------------------------------------------
    */
    public function storeMessage(array $data): Message  // receives message data, stores it in database, returns Message model
    {
        return Message::create($data);  // Uses Eloquent mass assignment.
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATE MESSAGES
    |--------------------------------------------------------------------------
    */
    public function paginateMessages(ChatRoom $room, int $limit = 50, ?string $cursor = null)
    {
        $userId = Auth::id();

        return $room->messages()
            ->with([
                'sender:id,name,profile',
                'replyTo.sender:id,name',
                'attachments.media',  // Load both the Attachment model and its MediaLibrary relation to avoid N+1 queries when rendering images/files.
                'reactions',
                'reads',
                'hiddenByUsers' => fn ($q) => $q->where('user_id', $userId), // containing only the current user's deletion record.
            ])
            /*
            | Exclude messages the current user deleted for themselves.
            | We use whereDoesntHave so the query stays set-based and uses
            | the composite index (message_id, user_id) on the junction table.
            */
            ->whereDoesntHave('hiddenByUsers', fn ($q) => $q->where('user_id', $userId)) // Do not return messages that the current user deleted.
            ->latest()->orderByDesc('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor);
    }
}
