<?php

namespace App\Policies;

use App\Models\CourseworkAttachment;
use App\Models\User;
use App\Services\ClassAccessService;
use Illuminate\Support\Facades\Gate;

class CourseworkAttachmentPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function download(User $user, CourseworkAttachment $attachment): bool
    {
        $revision = $attachment->revision;
        $submission = $revision->submission;
        if (! Gate::forUser($user)->allows('view', $submission)) {
            return false;
        }

        if ((int) $submission->student_id === (int) $user->id
            && $this->access->membership($user, $submission->assignment->schoolClass)?->role === 'student') {
            return true;
        }

        return $revision->status === 'submitted'
            && $this->access->teachingRole($user, $submission->assignment->schoolClass) !== null;
    }
}
