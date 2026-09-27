<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassAccessService;

class QuizPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function create(User $user, SchoolClass $schoolClass): bool
    {
        return ! $schoolClass->isArchived() && $this->access->teachingRole($user, $schoolClass) !== null;
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $quiz->status === 'published' && $this->access->content($user, $quiz->schoolClass);
    }

    public function manage(User $user, Quiz $quiz): bool
    {
        return $this->access->teachingRole($user, $quiz->schoolClass) !== null;
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $this->create($user, $quiz->schoolClass) && ! $quiz->isLocked();
    }

    public function publish(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }

    public function assign(User $user, Quiz $quiz): bool
    {
        return $this->manage($user, $quiz) && ! $quiz->schoolClass->isArchived() && $quiz->status === 'published';
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }
}
