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
}
