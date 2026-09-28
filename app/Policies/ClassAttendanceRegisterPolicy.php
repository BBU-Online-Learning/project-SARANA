<?php

namespace App\Policies;

use App\Models\ClassAttendanceRegister;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassAccessService;

class ClassAttendanceRegisterPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function viewAny(User $user, SchoolClass $schoolClass): bool
    {
        return $this->report($user, $schoolClass)
            || ($this->access->ready($user) && $schoolClass->studentEnrollments()->where('user_id', $user->id)->exists());
    }

    public function report(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->trashed() && ($this->access->teachingRole($user, $schoolClass) !== null || $this->access->administrator($user));
    }

    public function create(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && $this->access->teachingRole($user, $schoolClass) !== null;
    }

    public function view(User $user, ClassAttendanceRegister $register): bool
    {
        return $this->report($user, $register->schoolClass);
    }

    public function update(User $user, ClassAttendanceRegister $register): bool
    {
        return $register->finalized_at === null && $this->create($user, $register->schoolClass);
    }

    public function correct(User $user, ClassAttendanceRegister $register): bool
    {
        return $register->finalized_at !== null && $this->create($user, $register->schoolClass);
    }
}
