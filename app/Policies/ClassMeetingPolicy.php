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

    public function end(User $user, ClassMeeting $classMeeting): bool
    {
        return in_array($classMeeting->status, ['scheduled', 'ending'], true)
            && ($classMeeting->status === 'ending' || (now()->greaterThanOrEqualTo($classMeeting->starts_at->copy()->subMinutes(config('livekit.join_before_minutes')))
                && now()->lessThan($classMeeting->ends_at->copy()->addMinutes(config('livekit.join_after_minutes')))))
            && $this->manageJoinRequests($user, $classMeeting);
    }

    public function removeParticipant(User $user, ClassMeeting $classMeeting, User $target): bool
    {
        return $classMeeting->status === 'scheduled'
            && (int) $user->id !== (int) $target->id
            && $this->manageJoinRequests($user, $classMeeting)
            && $classMeeting->schoolClass->memberRecords()->where('user_id', $target->id)->where('role', 'student')->exists();
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
