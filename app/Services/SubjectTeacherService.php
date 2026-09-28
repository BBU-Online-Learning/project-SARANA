<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SubjectTeacherService
{
    public function __construct(private ClassManagementService $classes, private ClassAccessService $access) {}

    public function assign(User $actor, SchoolClass $schoolClass, int $subjectId, int $teacherId): TeacherSubjectAssignment
    {
        return $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($subjectId, $teacherId): TeacherSubjectAssignment {
            Gate::forUser($actor)->authorize('manageTeachers', $schoolClass);
            if (! $schoolClass->subjects()->whereKey($subjectId)->exists()) {
                throw ValidationException::withMessages(['subject_id' => 'Choose a subject in this class.']);
            }

            $teacher = User::query()->with('role')->lockForUpdate()->findOrFail($teacherId);
            if (! $this->access->eligibleTeacher($teacher)
                || ! in_array($this->access->membership($teacher, $schoolClass)?->role, ['owner', 'teacher'], true)) {
                throw ValidationException::withMessages(['user_id' => 'Choose a current teacher in this class.']);
            }
            if ($schoolClass->subjectTeacherAssignments()->where('subject_id', $subjectId)
                ->where('user_id', $teacherId)->where('active_slot', 1)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'This teacher already teaches that subject.']);
            }

            return $schoolClass->subjectTeacherAssignments()->create([
                'subject_id' => $subjectId,
                'user_id' => $teacherId,
                'academic_year_id' => $schoolClass->academic_year_id,
                'started_at' => now(),
                'active_slot' => 1,
            ]);
        });
    }

    public function end(User $actor, SchoolClass $schoolClass, TeacherSubjectAssignment $assignment): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($assignment): void {
            Gate::forUser($actor)->authorize('manageTeachers', $schoolClass);
            $locked = $schoolClass->subjectTeacherAssignments()->lockForUpdate()->findOrFail($assignment->id);
            if ($locked->active_slot !== 1) {
                throw ValidationException::withMessages(['assignment' => 'This subject assignment has already ended.']);
            }
            $locked->update(['ended_at' => now(), 'end_reason' => 'ended_by_manager', 'active_slot' => null]);
        });
    }

    public function canManageCoursework(User $user, SchoolClass $schoolClass, ?int $subjectId): bool
    {
        $role = $this->access->teachingRole($user, $schoolClass);
        if ($role === null) {
            return false;
        }
        if ($role === 'owner' || $subjectId === null) {
            return true;
        }

        $assignments = $schoolClass->subjectTeacherAssignments()
            ->where('subject_id', $subjectId)->where('active_slot', 1);

        return ! (clone $assignments)->exists()
            || (clone $assignments)->where('user_id', $user->id)->exists();
    }

    /** @param array<int, int|string> $retainedSubjectIds */
    public function endRemovedSubjects(SchoolClass $schoolClass, array $retainedSubjectIds): void
    {
        $schoolClass->subjectTeacherAssignments()->where('active_slot', 1)
            ->when($retainedSubjectIds !== [], fn ($query) => $query->whereNotIn('subject_id', $retainedSubjectIds))
            ->update(['ended_at' => now(), 'end_reason' => 'subject_removed', 'active_slot' => null]);
    }

    public function moveToYear(SchoolClass $schoolClass, int $academicYearId): void
    {
        $schoolClass->subjectTeacherAssignments()->where('active_slot', 1)->lockForUpdate()->get()
            ->each(function (TeacherSubjectAssignment $assignment) use ($schoolClass, $academicYearId): void {
                $assignment->update(['ended_at' => now(), 'end_reason' => 'academic_year_changed', 'active_slot' => null]);
                $schoolClass->subjectTeacherAssignments()->create([
                    'subject_id' => $assignment->subject_id,
                    'user_id' => $assignment->user_id,
                    'academic_year_id' => $academicYearId,
                    'started_at' => now(),
                    'active_slot' => 1,
                ]);
            });
    }
}
