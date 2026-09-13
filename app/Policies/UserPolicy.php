<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return Role::manageableNames($user) !== [];
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, User $target): bool
    {
        return ! $target->trashed()
            && $user->id !== $target->id
            && in_array($target->role?->name, Role::manageableNames($user), true);
    }

    public function update(User $user, User $target): bool
    {
        return $this->view($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->view($user, $target);
    }

    public function viewProfile(User $user, User $target): bool
    {
        $access = app(\App\Services\ClassAccessService::class);
        if (! $access->ready($user) || ! $access->ready($target)) {
            return false;
        }
        if ($user->is($target) || $this->view($user, $target)) {
            return true;
        }
        $sharesChat = $user->chatRooms()->whereHas('members', fn ($query) => $query->where('users.id', $target->id))->exists();
        $sharesClass = $user->schoolClasses()->whereHas('members', fn ($query) => $query->where('users.id', $target->id))->exists();

        return $sharesChat || $sharesClass;
    }
}
