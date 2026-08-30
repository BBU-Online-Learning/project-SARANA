<?php

namespace App\Services\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageSent;     // Used for: Real-time chat,Laravel Echo,WebSockets,Live message updates
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Repositories\Chat\MessageRepository;    // inside the service, database logic is delegated to a repository. This creates another abstraction layer.
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessageService
{
    protected MessageRepository $messageRepository;

    protected AttachmentService $attachmentService;
    // 1. This function runs AUTOMATICALLY the moment this class is born.
    public function __construct(
        MessageRepository $messageRepository,
        AttachmentService $attachmentService
    ) {
        $this->messageRepository = $messageRepository;
        $this->attachmentService = $attachmentService;
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MESSAGE
    |--------------------------------------------------------------------------
    */
    // This method: receives room, receives validated data, returns a Message model
    public function sendMessage(ChatRoom $room, array $data): Message  // This method guarantees a Message object is returned
    {
        return DB::transaction(function () use ($room, $data) {

            /*
            |--------------------------------------------------------------------------
            | MESSAGE TYPE
            |--------------------------------------------------------------------------
            */
            $attachments = $data['attachments'] ?? [];
            $hasAttachments = ! empty($attachments);
            // Detect voice note by checking whether every uploaded file is audio.
            $allAudio = $hasAttachments && collect($attachments)->every(function ($file) {
                $mimeType = $file->getMimeType() ?? '';
                $extension = strtolower($file->getClientOriginalExtension() ?? '');

                return str_starts_with($mimeType, 'audio/')
                    || in_array($extension, ['ogg', 'oga', 'webm', 'mp3', 'wav', 'm4a', 'aac', 'mpeg', 'mpga', 'mp4'], true);
            });

            $messageType = ! $hasAttachments
                ? 'text'
                : ($allAudio ? 'voice' : 'file');
            /*
            |--------------------------------------------------------------------------
            | STORE MESSAGE
            |--------------------------------------------------------------------------
            */
            $message = $this->messageRepository
                ->storeMessage([
                    'room_id' => $room->id,
                    'sender_id' => Auth::id(),
                    'client_uuid' => $data['client_uuid'] ?? null,
                    'message_type' => $messageType,
                    'body' => $data['body'] ?? null,
                    'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
                ]);

            /*
            |--------------------------------------------------------------------------
            | STORE ATTACHMENTS
            |--------------------------------------------------------------------------
            */

            if (!empty($attachments)) {
                $this->attachmentService->store(
                    $message,
                    $attachments
                );
            }
            /*
            |--------------------------------------------------------------------------
            | ROOM SYNC
            |--------------------------------------------------------------------------
            */

            $room->update(['last_message_at' => now()]); // Chat list UI usually sorts by latest activity:


            /*
            |--------------------------------------------------------------------------
            | LOAD RELATIONS
            |--------------------------------------------------------------------------
            */
            // Why load() Instead of with() :with() is used BEFORE query execution.Ex: Message::with(...)->find(1); But here message already exists.
            $message->load([
                'sender:id,name,profile',
                'attachments.media',
                'replyTo.sender',
            ]);     // loads relations afterward.

            /*
            |--------------------------------------------------------------------------
            | BROADCAST EVENT
            |--------------------------------------------------------------------------
            */

            broadcast(  // This is Laravel broadcasting system.  Frontend listeners instantly receive new message.
                new MessageSent($message)
            );  // Do NOT send broadcast back to current user.Without this:duplicate message rendering ,duplicated frontend append

            broadcast(
                new ConversationUpdated($message->load('sender'))
            )->toOthers();



            // pluck() with a raw COUNT alias needs select() first in some DB drivers —
            // safer to select explicitly:
            $unreadCounts = DB::table('chat_room_members')
                ->join('messages', 'messages.room_id', '=', 'chat_room_members.room_id')
                ->where('chat_room_members.room_id', $room->id)
                ->whereColumn('messages.sender_id', '!=', 'chat_room_members.user_id')
                ->whereNull('messages.deleted_for_everyone_at')
                ->where(function ($condition) {
                    $condition
                        ->whereNull('chat_room_members.last_read_at')
                        ->orWhereColumn('messages.created_at', '>', 'chat_room_members.last_read_at');
                })
                ->select('chat_room_members.user_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('chat_room_members.user_id')
                ->pluck('cnt', 'user_id');
            /*  unreadCounts=
                user_id 2 -> 5 unread
                user_id 4 -> 1 unread
                user_id 7 -> 12 unread ]
            */
            /*
            |--------------------------------------------------------------------------
            | SIDEBAR + UNREAD BROADCAST
            |--------------------------------------------------------------------------
            */
            foreach ($room->members as $member) {
                if ($member->id === Auth::id()) {
                    continue;
                }

                // Sidebar preview must use the human-readable preview text,
                // otherwise voice messages show up as blank or generic file names.
                broadcast(
                    new SidebarUpdated(
                        $member->id,
                        [
                            'room_id'     => $room->id,
                            'body'        => $message->previewText(),
                            'sender'      => $message->sender->name,
                            'created_at'  => now()->diffForHumans(),
                            'client_uuid' => $message->client_uuid,
                        ]
                    )
                );

                // Unread count broadcast — now reads from the pre-computed map, no extra query
                $unreadCount = $unreadCounts[$member->id] ?? 0;

                broadcast(
                    new UnreadCountUpdated($member->id, $room->id, $unreadCount)
                );
            }

            return $message;
        });
    }

    public function paginateMessages(ChatRoom $room, int $limit = 50, ?string $cursor = null)
    {
        return $this->messageRepository
            ->paginateMessages($room, $limit, $cursor);
    }
}
