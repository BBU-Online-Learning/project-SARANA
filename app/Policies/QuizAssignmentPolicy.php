<?php

namespace App\Policies;

use App\Models\QuizAssignment;
use App\Models\User;
use App\Services\ClassAccessService;

class QuizAssignmentPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function view(User $user, QuizAssignment $assignment): bool
    {
        return $this->access->content($user, $assignment->schoolClass)
            && ($this->access->teachingRole($user, $assignment->schoolClass) !== null
                || $assignment->students()->whereKey($user->id)->exists());
    }

    public function manage(User $user, QuizAssignment $assignment): bool
    {
        return ! $assignment->schoolClass->isArchived()
            && $this->access->teachingRole($user, $assignment->schoolClass) !== null;
    }

    public function start(User $user, QuizAssignment $assignment): bool
    {
        return $assignment->students()->whereKey($user->id)->exists()
            && $assignment->availabilityStatus() === 'available';
    }
}
