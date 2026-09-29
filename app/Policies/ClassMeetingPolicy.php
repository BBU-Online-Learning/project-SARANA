<?php

namespace App\Policies;

use App\Models\ClassMeeting;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassAccessService;

class ClassMeetingPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function viewAny(User $user, SchoolClass $schoolClass): bool
    {
        return $this->access->content($user, $schoolClass);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClassMeeting $classMeeting): bool
    {
        return $classMeeting->schoolClass !== null && $this->viewAny($user, $classMeeting->schoolClass);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && $this->access->teachingRole($user, $schoolClass) !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClassMeeting $classMeeting): bool
    {
        return $classMeeting->status === 'scheduled' && $classMeeting->starts_at->isFuture()
            && $this->create($user, $classMeeting->schoolClass);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function cancel(User $user, ClassMeeting $classMeeting): bool
    {
        return $this->update($user, $classMeeting);
    }

    public function manageJoinRequests(User $user, ClassMeeting $classMeeting): bool
    {
        return $this->view($user, $classMeeting)
            && $this->access->teachingRole($user, $classMeeting->schoolClass) !== null;
    }

    public function issueToken(User $user, ClassMeeting $classMeeting): bool
    {
        if (! $this->view($user, $classMeeting)) {
            return false;
        }

        if ($this->manageJoinRequests($user, $classMeeting)) {
            return true;
        }

        return $classMeeting->joinRequests()
            ->where('requester_user_id', $user->id)
            ->first()?->admitsCurrentEntry() ?? false;
    }
}
