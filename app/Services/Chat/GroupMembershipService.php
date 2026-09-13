<?php

namespace App\Services\Chat;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GroupMembershipService
{
    public function __construct(private ChatAccessService $access) {}

    public function create(User $creator, string $name, array $members): ChatRoom
    {
        return $this->access->synchronized(function () use ($creator, $name, $members): ChatRoom {
            $creator = User::query()->lockForUpdate()->findOrFail($creator->id);
            abort_unless($this->access->ready($creator), 403);
            $ids = $this->eligibleMembers($members, $creator->id);
            $room = ChatRoom::create(['type' => 'group', 'name' => $name, 'created_by' => $creator->id]);
            $room->members()->attach($creator->id, ['role' => 'owner', 'joined_at' => now()]);
            foreach ($ids as $id) {
                $room->members()->attach($id, ['role' => 'member', 'joined_at' => now()]);
            }

            return $room;
        });
    }

    public function rename(User $actor, ChatRoom $room, string $name, ?string $description = null, ?string $avatarPath = null): ?string
    {
        return $this->access->withRoom($actor, $room, function (User $actor, ChatRoom $room) use ($name, $description, $avatarPath): ?string {
            Gate::forUser($actor)->authorize('manageGroup', $room);
            $oldAvatarPath = $room->managedAvatarPath();
            $attributes = ['name' => $name, 'description' => $description];
            if ($avatarPath !== null) {
                $attributes['avatar'] = $avatarPath;
            }
            $room->update($attributes);

            return $oldAvatarPath;
        });
    }

    public function add(User $actor, ChatRoom $room, array $members): void
    {
        $this->access->withRoom($actor, $room, function (User $actor, ChatRoom $room) use ($members): void {
            Gate::forUser($actor)->authorize('manageGroup', $room);
            $ids = $this->eligibleMembers($members, $actor->id);
            $existing = $room->roomMembers()->pluck('user_id')->all();
            $new = array_values(array_diff($ids, $existing));
            if (count($existing) + count($new) > 31) {
                throw ValidationException::withMessages(['members' => 'Groups support at most 31 members, including the owner.']);
            }
            foreach ($new as $id) {
                $room->members()->attach($id, ['role' => 'member', 'joined_at' => now()]);
            }
        });
    }

    public function remove(User $actor, ChatRoom $room, int $targetId, bool $leaving = false): void
    {
        $this->access->withRoom($actor, $room, function (User $actor, ChatRoom $room) use ($targetId, $leaving): void {
            abort_unless($room->type === 'group', 404);
            if (! $leaving) {
                Gate::forUser($actor)->authorize('manageGroup', $room);
            } else {
                abort_unless($actor->id === $targetId, 403);
            }
            $membership = $room->roomMembers()->where('user_id', $targetId)->lockForUpdate()->firstOrFail();
            if ($membership->role === 'owner') {
                throw ValidationException::withMessages(['member' => 'The owner cannot leave or be removed. The group must retain its owner.']);
            }
            $membership->delete();
        });
    }

    private function eligibleMembers(array $members, int $creatorId): array
    {
        $ids = collect($members)->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $creatorId)->unique()->values();
        if ($ids->count() > 30) {
            throw ValidationException::withMessages(['members' => 'Select at most 30 members.']);
        }
        $users = User::with('role')->whereIn('id', $ids)->lockForUpdate()->get();
        if ($users->count() !== $ids->count() || $users->contains(fn ($user) => ! $this->access->ready($user))) {
            throw ValidationException::withMessages(['members' => 'All selected members must have active accounts and completed onboarding.']);
        }

        return $ids->all();
    }
}
