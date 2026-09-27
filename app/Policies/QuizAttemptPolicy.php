<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\ClassAccessService;

class QuizAttemptPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function view(User $user, QuizAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id
            || $this->access->teachingRole($user, $attempt->assignment->schoolClass) !== null;
    }

    public function update(User $user, QuizAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id && $attempt->status === 'in_progress';
    }

    public function grade(User $user, QuizAttempt $attempt): bool
    {
        return $this->access->teachingRole($user, $attempt->assignment->schoolClass) !== null;
    }
}
