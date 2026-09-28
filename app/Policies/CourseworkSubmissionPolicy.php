<?php

namespace App\Policies;

use App\Models\CourseworkAssignment;
use App\Models\CourseworkSubmission;
use App\Models\User;
use App\Services\ClassAccessService;

class CourseworkSubmissionPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function create(User $user, CourseworkAssignment $assignment): bool
    {
        $schoolClass = $assignment->schoolClass;

        return $assignment->status === 'published' && ! $schoolClass->isArchived()
            && $this->access->content($user, $schoolClass)
            && $this->access->membership($user, $schoolClass)?->role === 'student';
    }

    public function view(User $user, CourseworkSubmission $submission): bool
    {
        $schoolClass = $submission->assignment->schoolClass;
        if (! $this->access->content($user, $schoolClass)) {
            return false;
        }

        if ((int) $submission->student_id === (int) $user->id
            && $this->access->membership($user, $schoolClass)?->role === 'student') {
            return true;
        }

        return $this->access->teachingRole($user, $schoolClass) !== null
            && $submission->revisions()->where('status', 'submitted')->exists();
    }

    public function saveDraft(User $user, CourseworkSubmission $submission): bool
    {
        return (int) $submission->student_id === (int) $user->id
            && $submission->status === 'draft' && $this->create($user, $submission->assignment);
    }

    public function submit(User $user, CourseworkSubmission $submission): bool
    {
        return $this->saveDraft($user, $submission);
    }

    public function resubmit(User $user, CourseworkSubmission $submission): bool
    {
        return (int) $submission->student_id === (int) $user->id
            && $submission->status === 'submitted'
            && $submission->assignment->allow_resubmissions
            && $this->create($user, $submission->assignment);
    }

    public function grade(User $user, CourseworkSubmission $submission): bool
    {
        $schoolClass = $submission->assignment->schoolClass;

        return ! $schoolClass->isArchived()
            && $this->access->teachingRole($user, $schoolClass) !== null
            && $submission->revisions()->where('status', 'submitted')->exists();
    }
}
