<?php

namespace App\Services\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageSent;     // Used for: Real-time chat,Laravel Echo,WebSockets,Live message updates
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Repositories\Chat\MessageRepository;    // inside the service, database logic is delegated to a repository. This creates another abstraction layer.
use App\Rules\SafeChatAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $isRecordedVoice = $hasAttachments && ($data['attachment_context'] ?? null) === 'voice';
            $allImages = $hasAttachments && collect($attachments)->every(
                fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'image/')
            );
            $allVideos = $hasAttachments && collect($attachments)->every(
                fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'video/')
                    && in_array(strtolower((string) $file->getClientOriginalExtension()), ['mp4', 'webm'], true)
            );

            $messageType = filled($data['sticker_id'] ?? null)
                ? 'sticker'
                : match (true) {
                    ! $hasAttachments => 'text',
                    $isRecordedVoice => 'voice',
                    $allImages => 'image',
                    $allVideos => 'video',
                    default => 'file',
                };

            $clientUuid = $data['client_uuid'] ?? null;
            $messageAttributes = [
                'room_id' => $room->id,
                'sender_id' => Auth::id(),
                'message_type' => $messageType,
                'body' => $data['body'] ?? null,
                'sticker_id' => $data['sticker_id'] ?? null,
                'reply_to_message_id' => isset($data['reply_to_message_id']) ? (int) $data['reply_to_message_id'] : null,
            ];

            if ($clientUuid) {
                $existing = Message::withTrashed()->where('client_uuid', $clientUuid)->first();
                if ($existing) {
                    $this->ensureMatchingRetry($existing, $messageAttributes, $attachments);

                    return $existing;
                }
            }
            /*
            |--------------------------------------------------------------------------
            | STORE MESSAGE
            |--------------------------------------------------------------------------
            */
            $message = $clientUuid
                ? Message::query()->firstOrCreate(['client_uuid' => $clientUuid], $messageAttributes)
                : $this->messageRepository->storeMessage($messageAttributes);

            if (! $message->wasRecentlyCreated) {
                $this->ensureMatchingRetry($message, $messageAttributes, $attachments);

                return $message;
            }

            /*
            |--------------------------------------------------------------------------
            | STORE ATTACHMENTS
            |--------------------------------------------------------------------------
            */

            if (! empty($attachments)) {
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

            Message::resolveConnection()->afterCommit(function () use ($message): void {
                rescue(fn () => broadcast(new MessageSent($message)), report: true);
            });

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
                ->whereNull('messages.deleted_at')
                ->where(fn ($query) => $query->whereNull('chat_room_members.legacy_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'chat_room_members.legacy_read_at'))
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('message_reads')
                        ->whereColumn('message_reads.message_id', 'messages.id')
                        ->whereColumn('message_reads.user_id', 'chat_room_members.user_id')
                        ->whereNotNull('message_reads.read_at');
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('message_user_deletions')
                        ->whereColumn('message_user_deletions.message_id', 'messages.id')
                        ->whereColumn('message_user_deletions.user_id', 'chat_room_members.user_id');
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
                            'room_id' => $room->id,
                            'body' => $message->previewText(),
                            'sender' => $message->sender->name,
                            'created_at' => now()->diffForHumans(),
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

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, \Illuminate\Http\UploadedFile>  $attachments
     */
    private function ensureMatchingRetry(Message $message, array $attributes, array $attachments): void
    {
        $matchesMessage = ! $message->trashed()
            && $message->deleted_for_everyone_at === null
            && $message->room_id === $attributes['room_id']
            && $message->sender_id === $attributes['sender_id']
            && $message->message_type === $attributes['message_type']
            && $message->body === $attributes['body']
            && $message->sticker_id === $attributes['sticker_id']
            && $message->reply_to_message_id === $attributes['reply_to_message_id'];

        $storedFiles = $message->attachments()->orderBy('id')->get()
            ->map(fn ($attachment): array => [$attachment->original_name, (int) $attachment->file_size])
            ->values()->all();
        $retriedFiles = collect($attachments)
            ->map(fn ($attachment): array => [
                SafeChatAttachment::filename($attachment->getClientOriginalName(), $attachment->getClientOriginalExtension()),
                (int) $attachment->getSize(),
            ])->values()->all();

        if (! $matchesMessage || $storedFiles !== $retriedFiles) {
            throw ValidationException::withMessages([
                'client_uuid' => 'This send identifier has already been used for a different message.',
            ]);
        }
    }
}
