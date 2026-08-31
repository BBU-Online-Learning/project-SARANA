<?php

namespace App\Services\Chat;

use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountManagementService;
use Closure;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Validation\ValidationException;

class ChatAccessService
{
    public function ready(User $user): bool
    {
        return ! $user->trashed() && $user->status === 'active' && $user->google2fa_enabled
            && ! $user->must_change_password && $user->role?->status
            && in_array($user->role->name, Role::NAMES, true);
    }

    public function access(User $user, ChatRoom $room): bool
    {
        return ! $room->trashed() && $this->ready($user)
            && $room->roomMembers()->where('user_id', $user->id)->exists();
    }

    public function owner(ChatRoom $room): ?ChatRoomMember
    {
        $owners = $room->roomMembers()->where('role', 'owner')->limit(2)->get();

        return $owners->count() === 1 ? $owners->first() : null;
    }

    public function manage(User $user, ChatRoom $room): bool
    {
        return $room->type === 'group' && $this->access($user, $room)
            && $this->owner($room)?->user_id === $user->id;
    }

    public function synchronized(Closure $callback): mixed
    {
        $connection = ChatRoom::resolveConnection();
        if ($connection->getDriverName() === 'mysql') {
            $engines = $connection->table('information_schema.TABLES')->where('TABLE_SCHEMA', $connection->getDatabaseName())
                ->whereIn('TABLE_NAME', ['chat_rooms', 'chat_room_members', 'messages'])->pluck('ENGINE');
            if ($engines->count() !== 3 || $engines->contains(fn ($engine) => strtolower($engine) !== 'innodb')) {
                throw ValidationException::withMessages(['group' => 'Back up the database and run the chat ownership/transaction migration before changing chats.']);
            }
        }

        return app(AccountManagementService::class)->synchronized($callback);
    }

    public function withRoom(User $actor, ChatRoom $room, Closure $callback): mixed
    {
        return $this->synchronized(function () use ($actor, $room, $callback): mixed {
            $version = $actor->auth_version;
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $room = ChatRoom::query()->lockForUpdate()->findOrFail($room->id);
            abort_unless($actor->auth_version === $version && $this->access($actor, $room), 403);

            return $callback($actor, $room);
        });
    }

    public function subscription(User $user, int $membershipId): bool
    {
        $membership = ChatRoomMember::with('room')->find($membershipId);
        $user = User::find($user->id);

        return $user && $membership && $membership->room?->type === 'group'
            && $membership->user_id === $user->id && $this->access($user, $membership->room);
    }

    public function broadcastChannels(int $roomId): array
    {
        $room = ChatRoom::find($roomId);
        if (! $room) {
            return [];
        }
        if ($room->type === 'direct') {
            return [new PresenceChannel('chat.room.'.$room->id)];
        }

        return $room->roomMembers()->with('user.role')->get()
            ->filter(fn ($membership) => $membership->user && $this->ready($membership->user))
            ->map(fn ($membership) => new PrivateChannel('chat.membership.'.$membership->id))->values()->all();
    }

    public function userChannels(int $userId, int $roomId): array
    {
        $user = User::find($userId);
        $room = ChatRoom::find($roomId);

        return $user && $room && $this->access($user, $room) ? [new PrivateChannel('user.'.$userId)] : [];
    }
}
