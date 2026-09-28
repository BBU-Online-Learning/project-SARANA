<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\StudentClassEnrollment;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AcademicMembershipService
{
    public function recordChange(SchoolClass $schoolClass, User $user, ?string $oldRole, ?string $newRole, string $reason): void
    {
        if ($oldRole === $newRole) {
            return;
        }

        $changedAt = now();
        if ($oldRole !== null) {
            $oldModel = $this->modelForRole($oldRole);
            $active = $oldModel::query()->where('school_class_id', $schoolClass->id)
                ->where('user_id', $user->id)->where('active_slot', 1)->lockForUpdate()->first();
            if (! $active) {
                $membership = $schoolClass->memberRecords()->where('user_id', $user->id)->first();
                $attributes = [
                    'school_class_id' => $schoolClass->id,
                    'user_id' => $user->id,
                    'academic_year_id' => $schoolClass->academic_year_id,
                    'started_at' => $membership?->joined_at ?? $changedAt,
                    'source' => 'reconstructed_from_membership',
                    'active_slot' => 1,
                ];
                $active = $oldModel::query()->create($attributes + ($oldRole === 'student' ? [] : ['role' => $oldRole]));
            }
            $active->update(['ended_at' => $changedAt, 'end_reason' => $reason, 'active_slot' => null]);
        }

        if ($newRole !== null) {
            $newModel = $this->modelForRole($newRole);
            $attributes = [
                'school_class_id' => $schoolClass->id,
                'user_id' => $user->id,
                'academic_year_id' => $schoolClass->academic_year_id,
                'started_at' => $changedAt,
                'source' => 'membership',
                'active_slot' => 1,
            ];
            $newModel::query()->create($attributes + ($newRole === 'student' ? [] : ['role' => $newRole]));
        }
    }

    public function moveToYear(SchoolClass $schoolClass, int $academicYearId): void
    {
        if ((int) $schoolClass->academic_year_id === $academicYearId) {
            return;
        }

        $changedAt = now();
        foreach ([StudentClassEnrollment::class, TeacherClassAssignment::class] as $model) {
            $model::query()->where('school_class_id', $schoolClass->id)->where('active_slot', 1)
                ->lockForUpdate()->get()->each(function (Model $active) use ($model, $academicYearId, $changedAt): void {
                    $active->update(['ended_at' => $changedAt, 'end_reason' => 'academic_year_changed', 'active_slot' => null]);
                    $attributes = [
                        'school_class_id' => $active->school_class_id,
                        'user_id' => $active->user_id,
                        'academic_year_id' => $academicYearId,
                        'started_at' => $changedAt,
                        'source' => 'academic_year_change',
                        'active_slot' => 1,
                    ];
                    $model::query()->create($attributes + ($active instanceof TeacherClassAssignment ? ['role' => $active->role] : []));
                });
        }
    }

    /** @return class-string<Model> */
    private function modelForRole(string $role): string
    {
        return $role === 'student' ? StudentClassEnrollment::class : TeacherClassAssignment::class;
    }
}
