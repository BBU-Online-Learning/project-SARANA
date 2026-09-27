<?php

namespace App\Services\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageSent;     // Used for: Real-time chat,Laravel Echo,WebSockets,Live message updates
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Repositories\Chat\MessageRepository;    // inside the service, database logic is delegated to a repository. This creates another abstraction layer.
use App\Rules\SafeChatAttachment;
use Illuminate\Support\Collection;
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
            $forwardedMessage = ($data['forwarded_message'] ?? null) instanceof Message
                ? $data['forwarded_message']
                : null;
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

            $messageType = $forwardedMessage?->message_type ?? (filled($data['sticker_id'] ?? null)
                ? 'sticker'
                : match (true) {
                    ! $hasAttachments => 'text',
                    $isRecordedVoice => 'voice',
                    $allImages => 'image',
                    $allVideos => 'video',
                    default => 'file',
                });

            $forwardedSenderId = $forwardedMessage?->forwarded_from_sender_id ?? $forwardedMessage?->sender_id;
            $forwardedSenderName = $forwardedMessage?->forwarded_from_sender_name ?? $forwardedMessage?->sender?->name;

            $clientUuid = $data['client_uuid'] ?? null;
            $messageAttributes = [
                'room_id' => $room->id,
                'sender_id' => Auth::id(),
                'message_type' => $messageType,
                'body' => $forwardedMessage?->body ?? ($data['body'] ?? null),
                'sticker_id' => $forwardedMessage?->sticker_id ?? ($data['sticker_id'] ?? null),
                'reply_to_message_id' => isset($data['reply_to_message_id']) ? (int) $data['reply_to_message_id'] : null,
                'forwarded_from_message_id' => $forwardedMessage?->forwarded_from_message_id ?? $forwardedMessage?->id,
                'forwarded_from_sender_id' => $forwardedSenderId,
                'forwarded_from_sender_name' => $forwardedSenderName,
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

            if ($forwardedMessage) {
                $this->attachmentService->forward($message, $forwardedMessage->attachments);
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
                'attachments.sourceAttachment.media',
                'replyTo.sender',
                'forwardedFromSender:id,name',
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
                $unreadCount = $unreadCounts[$member->id] ?? 0;

                // Sidebar preview must use the human-readable preview text,
                // otherwise voice messages show up as blank or generic file names.
                broadcast(
                    new SidebarUpdated(
                        $member->id,
                        [
                            'room_id' => $room->id,
                            'message_id' => $message->id,
                            'sender_id' => $message->sender_id,
                            'body' => $message->previewText(),
                            'sender' => $message->sender->name,
                            'created_at' => now()->diffForHumans(),
                            'client_uuid' => $message->client_uuid,
                            'unread_count' => $unreadCount,
                        ]
                    )
                );

                if ($member->id === Auth::id()) {
                    continue;
                }

                $member->notify(new ActivityNotification(
                    'message',
                    'New message from '.$message->sender->name,
                    $message->previewText(),
                    route('chat.index', ['room' => $room->id], false),
                    $room->id,
                ));

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
     * @param  array<int, int>  $messageIds
     * @param  array<int, int>  $roomIds
     * @return Collection<int, Message>
     */
    public function forwardMessages(User $actor, array $messageIds, array $roomIds, ?string $note = null): Collection
    {
        return DB::transaction(function () use ($actor, $messageIds, $roomIds, $note): Collection {
            $sourceMessages = Message::query()
                ->with(['sender:id,name', 'attachments.sourceAttachment.media', 'attachments.media', 'room'])
                ->whereKey($messageIds)
                ->whereNull('deleted_for_everyone_at')
                ->whereNotIn('message_type', ['call', 'system_notification'])
                ->whereDoesntHave('hiddenByUsers', fn ($query) => $query->where('user_id', $actor->id))
                ->get()
                ->sortBy(fn (Message $message): int => array_search($message->id, $messageIds, true))
                ->values();

            if ($sourceMessages->count() !== count($messageIds)
                || $sourceMessages->pluck('room_id')->unique()->count() !== 1
                || ! app(ChatAccessService::class)->access($actor, $sourceMessages->first()->room)) {
                throw ValidationException::withMessages([
                    'message_ids' => 'One or more messages are unavailable to forward.',
                ]);
            }

            if ($sourceMessages->flatMap->attachments->contains(
                fn ($attachment): bool => $attachment->mediaForDelivery() === null
            )) {
                throw ValidationException::withMessages([
                    'message_ids' => 'One or more forwarded files are no longer available.',
                ]);
            }

            $destinationRooms = ChatRoom::query()
                ->with('members')
                ->whereKey($roomIds)
                ->whereHas('members', fn ($query) => $query->where('users.id', $actor->id))
                ->get()
                ->sortBy(fn (ChatRoom $room): int => array_search($room->id, $roomIds, true))
                ->values();

            if ($destinationRooms->count() !== count($roomIds)
                || $destinationRooms->contains(fn (ChatRoom $room): bool => ! app(ChatAccessService::class)->access($actor, $room))) {
                throw ValidationException::withMessages([
                    'room_ids' => 'One or more destination conversations are unavailable.',
                ]);
            }

            $forwarded = collect();

            foreach ($destinationRooms as $room) {
                if (filled($note)) {
                    $forwarded->push($this->sendMessage($room, ['body' => $note]));
                }

                foreach ($sourceMessages as $sourceMessage) {
                    $forwarded->push($this->sendMessage($room, ['forwarded_message' => $sourceMessage]));
                }
            }

            return $forwarded;
        });
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
