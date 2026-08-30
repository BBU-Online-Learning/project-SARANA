<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\SchoolClassMember;
use App\Models\User;

class ClassAccessService
{
    public function eligible(User $user): bool
    {
        return ! $user->trashed() && $user->status === 'active'
            && $user->role?->status && in_array($user->role->name, Role::NAMES, true);
    }

    public function ready(User $user): bool
    {
        return $this->eligible($user) && $user->google2fa_enabled && ! $user->must_change_password;
    }

    public function administrator(User $user): bool
    {
        return $this->ready($user) && in_array($user->role->name, [Role::SUPER_ADMIN, Role::ADMIN], true);
    }

    public function eligibleTeacher(User $user): bool
    {
        return $this->eligible($user) && $user->role->name === Role::TEACHER;
    }

    public function membership(User $user, SchoolClass $schoolClass): ?SchoolClassMember
    {
        return $schoolClass->memberRecords()->where('user_id', $user->id)->first();
    }

    public function teachingRole(User $user, SchoolClass $schoolClass): ?string
    {
        if ($schoolClass->trashed() || ! $this->ready($user) || ! $this->eligibleTeacher($user)) {
            return null;
        }

        $role = $this->membership($user, $schoolClass)?->role;

        return in_array($role, ['owner', 'teacher'], true) ? $role : null;
    }

    public function content(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->trashed() && $this->ready($user)
            && in_array($this->membership($user, $schoolClass)?->role, ['owner', 'teacher', 'student'], true);
    }

    public function channel(User $user, SchoolClass $schoolClass, SchoolClassChannel $channel): bool
    {
        return ! $channel->trashed() && (int) $channel->school_class_id === (int) $schoolClass->id
            && $this->content($user, $schoolClass);
    }

    public function subscription(User $user, int $membershipId): bool
    {
        $user = User::query()->find($user->id);
        $membership = SchoolClassMember::query()->with('schoolClass')->find($membershipId);

        return $user && $membership && $membership->schoolClass
            && (int) $membership->user_id === (int) $user->id
            && $this->content($user, $membership->schoolClass);
    }
}
