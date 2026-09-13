<?php

namespace App\Services\Chat;

use App\Events\Chat\ReadReceiptUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReadReceiptService
{
    public function visibleIncoming(ChatRoom $room, int $userId): Builder
    {
        return Message::query()->where('room_id', $room->id)
            ->where('sender_id', '!=', $userId)->whereNull('deleted_for_everyone_at')
            ->whereDoesntHave('hiddenByUsers', fn (Builder $query) => $query->where('user_id', $userId));
    }

    public function unreadCount(ChatRoom $room, int $userId): int
    {
        return $this->visibleIncoming($room, $userId)
            ->whereNotExists(function ($query) use ($userId): void {
                $query->selectRaw('1')->from('chat_room_members')
                    ->whereColumn('chat_room_members.room_id', 'messages.room_id')
                    ->where('chat_room_members.user_id', $userId)
                    ->whereColumn('messages.created_at', '<=', 'chat_room_members.legacy_read_at');
            })
            ->whereDoesntHave('reads', fn (Builder $query) => $query->where('user_id', $userId)->whereNotNull('read_at'))->count();
    }

    public function mark(User $actor, ChatRoom $room, int $targetId): array
    {
        return app(ChatAccessService::class)->withRoom($actor, $room, function (User $actor, ChatRoom $room) use ($targetId): array {
            $target = $room->messages()->whereNull('deleted_for_everyone_at')
                ->whereDoesntHave('hiddenByUsers', fn (Builder $query) => $query->where('user_id', $actor->id))
                ->findOrFail($targetId);
            $readAt = now('UTC');
            $changed = 0;
            $query = $this->visibleIncoming($room, $actor->id)
                ->where(fn (Builder $query) => $query->where('created_at', '<', $target->created_at)
                    ->orWhere(fn (Builder $query) => $query->where('created_at', $target->created_at)->where('id', '<=', $target->id)))
                ->whereDoesntHave('reads', fn (Builder $query) => $query->where('user_id', $actor->id)->whereNotNull('read_at'));
            $query->chunkById(250, function ($messages) use ($actor, $readAt, &$changed): void {
                $changed += MessageRead::query()->where('user_id', $actor->id)->whereIn('message_id', $messages->modelKeys())
                    ->whereNull('read_at')->update(['read_at' => $readAt, 'updated_at' => $readAt]);
                $changed += MessageRead::query()->insertOrIgnore($messages->map(fn (Message $message): array => [
                    'message_id' => $message->id, 'user_id' => $actor->id, 'read_at' => $readAt,
                    'created_at' => $readAt, 'updated_at' => $readAt,
                ])->all());
            });
            $room->roomMembers()->where('user_id', $actor->id)
                ->where(fn (Builder $query) => $query->whereNull('last_read_at')->orWhere('last_read_at', '<', $target->created_at))
                ->update(['last_read_at' => $target->created_at]);
            $unreadCount = $this->unreadCount($room, $actor->id);
            if ($changed > 0) {
                Message::resolveConnection()->afterCommit(function () use ($room, $actor, $readAt, $target, $unreadCount): void {
                    rescue(fn () => event(new ReadReceiptUpdated($room->id, $actor->id, $readAt->toISOString(), '', $target->id)), report: true);
                    rescue(fn () => event(new UnreadCountUpdated($actor->id, $room->id, $unreadCount)), report: true);
                });
            }

            return ['success' => true, 'inserted_count' => $changed, 'unread_count' => $unreadCount];
        });
    }

    public function details(Message $message, ChatRoom $room): array
    {
        $room->loadMissing('members.role');
        $members = $room->members->filter(fn (User $user): bool => $user->id !== $message->sender_id && app(ChatAccessService::class)->ready($user))->keyBy('id');
        $message->loadMissing('reads');
        $readers = $message->reads->filter(fn (MessageRead $read): bool => $read->read_at !== null && $members->has($read->user_id))
            ->sortBy('read_at')->map(fn (MessageRead $read): array => [
                'id' => $read->user_id, 'name' => $members[$read->user_id]->name,
                'avatar' => $members[$read->user_id]->profileUrl(), 'read_at' => $read->read_at->toISOString(),
            ])->values();

        return ['read_count' => $readers->count(), 'eligible_reader_count' => $members->count(), 'readers' => $readers->all()];
    }
}
