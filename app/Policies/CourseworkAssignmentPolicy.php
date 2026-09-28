<?php

namespace App\Policies;

use App\Models\CourseworkAssignment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassAccessService;
use App\Services\SubjectTeacherService;

class CourseworkAssignmentPolicy
{
    public function __construct(private ClassAccessService $access, private SubjectTeacherService $subjectTeachers) {}

    public function viewAny(User $user, SchoolClass $schoolClass): bool
    {
        return $this->access->content($user, $schoolClass);
    }

    public function view(User $user, CourseworkAssignment $assignment): bool
    {
        $schoolClass = $assignment->schoolClass;

        return ($this->access->teachingRole($user, $schoolClass) !== null
                && ($assignment->status !== 'draft' || $this->subjectTeachers->canManageCoursework($user, $schoolClass, $assignment->subject_id)))
            || ($assignment->status !== 'draft' && $this->access->content($user, $schoolClass)
                && $this->access->membership($user, $schoolClass)?->role === 'student');
    }

    public function create(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && $this->access->teachingRole($user, $schoolClass) !== null;
    }

    public function update(User $user, CourseworkAssignment $assignment): bool
    {
        return $assignment->status === 'draft' && $this->create($user, $assignment->schoolClass)
            && $this->subjectTeachers->canManageCoursework($user, $assignment->schoolClass, $assignment->subject_id);
    }

    public function close(User $user, CourseworkAssignment $assignment): bool
    {
        return $assignment->status === 'published' && $this->create($user, $assignment->schoolClass)
            && $this->subjectTeachers->canManageCoursework($user, $assignment->schoolClass, $assignment->subject_id);
    }
}
