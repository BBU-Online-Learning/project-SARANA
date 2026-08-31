<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Models\User;
use App\Services\ClassAccessService;

class SchoolClassPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function create(User $user): bool
    {
        return $this->access->ready($user)
            && ($this->access->administrator($user) || $this->access->eligibleTeacher($user));
    }

    public function view(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->trashed()
            && ($this->access->administrator($user) || $this->viewContent($user, $schoolClass));
    }

    public function viewContent(User $user, SchoolClass $schoolClass): bool
    {
        return $this->access->content($user, $schoolClass);
    }

    public function viewChannel(User $user, SchoolClass $schoolClass, SchoolClassChannel $channel): bool
    {
        return $this->access->channel($user, $schoolClass, $channel);
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && $this->manageLifecycle($user, $schoolClass);
    }

    public function manageLifecycle(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->trashed() && ($this->access->administrator($user)
            || $this->access->teachingRole($user, $schoolClass) === 'owner');
    }

    public function regenerateCode(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && $this->access->teachingRole($user, $schoolClass) === 'owner';
    }

    public function sendMessage(User $user, SchoolClass $schoolClass, SchoolClassChannel $channel): bool
    {
        return ! $schoolClass->isArchived() && $this->viewChannel($user, $schoolClass, $channel)
            && (! $channel->isAnnouncement() || $this->access->teachingRole($user, $schoolClass) !== null);
    }

    public function manageOwnMessage(User $user, SchoolClass $schoolClass, SchoolClassChannel $channel, SchoolClassChannelMessage $message): bool
    {
        return $this->sendMessage($user, $schoolClass, $channel)
            && $message->school_class_channel_id === $channel->id
            && $message->sender_id === $user->id;
    }

    public function manageMembers(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && ! $schoolClass->trashed() && ($this->access->administrator($user)
            || $this->access->teachingRole($user, $schoolClass) !== null);
    }

    public function manageTeachers(User $user, SchoolClass $schoolClass): bool
    {
        return $this->update($user, $schoolClass);
    }

    public function manageChannels(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && $this->access->teachingRole($user, $schoolClass) !== null;
    }

    public function addMember(User $user, SchoolClass $schoolClass, User $target, string $role): bool
    {
        if (! $this->manageMembers($user, $schoolClass) || ! $this->access->eligible($target)) {
            return false;
        }

        if ($role === 'teacher') {
            return $this->manageTeachers($user, $schoolClass) && $this->access->eligibleTeacher($target);
        }

        if ($role !== 'student') {
            return false;
        }

        if (in_array($target->role->name, [Role::SUPER_ADMIN, Role::ADMIN], true)) {
            return $this->access->administrator($user)
                && ($user->id === $target->id || $user->role->name === Role::SUPER_ADMIN);
        }

        return true;
    }

    public function removeMember(User $user, SchoolClass $schoolClass, User $target): bool
    {
        $membership = $this->access->membership($target, $schoolClass);
        if (! $membership || $membership->role === 'owner' || ! $this->manageMembers($user, $schoolClass)) {
            return false;
        }

        if (in_array($target->role?->name, [Role::SUPER_ADMIN, Role::ADMIN], true)) {
            return $this->access->administrator($user)
                && ($user->id === $target->id || $user->role->name === Role::SUPER_ADMIN);
        }

        return $membership->role === 'student' || $this->manageTeachers($user, $schoolClass);
    }

    public function enroll(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && ! $schoolClass->trashed() && $this->access->administrator($user)
            && ! $this->access->membership($user, $schoolClass);
    }

    public function transferOwnership(User $user, SchoolClass $schoolClass): bool
    {
        return $this->update($user, $schoolClass);
    }
}
