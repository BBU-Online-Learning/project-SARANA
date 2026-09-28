<?php

namespace App\Services;

use App\Models\ClassAttendanceRecord;
use App\Models\ClassAttendanceRegister;
use App\Models\SchoolClass;
use App\Models\StudentClassEnrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClassAttendanceService
{
    public function __construct(private ClassManagementService $classes) {}

    public function open(User $actor, SchoolClass $schoolClass, string $date): ClassAttendanceRegister
    {
        return $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($date): ClassAttendanceRegister {
            Gate::forUser($actor)->authorize('create', [ClassAttendanceRegister::class, $schoolClass]);
            $existing = $schoolClass->attendanceRegisters()->whereDate('attendance_date', $date)->first();
            if ($existing) {
                return $existing;
            }

            $day = CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
            $register = $schoolClass->attendanceRegisters()->create([
                'attendance_date' => $day->toDateString(),
                'opened_by' => $actor->id,
                'roster_snapshot_at' => now(),
            ]);
            $enrollments = StudentClassEnrollment::query()->with('user')
                ->where('school_class_id', $schoolClass->id)
                ->where('started_at', '<', $day->addDay()->toDateTimeString())
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $day->toDateTimeString()))
                ->orderByDesc('started_at')->orderByDesc('id')->get()->unique('user_id');

            foreach ($enrollments as $enrollment) {
                $register->records()->create([
                    'student_id' => $enrollment->user_id,
                    'student_class_enrollment_id' => $enrollment->id,
                    'student_name_snapshot' => $enrollment->user?->name ?? 'Former student',
                ]);
            }

            return $register;
        });
    }

    /** @param array<int, array{status: string|null, note?: string|null}> $entries */
    public function bulk(User $actor, SchoolClass $schoolClass, ClassAttendanceRegister $register, array $entries): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($register, $entries): void {
            $register = $this->lockedRegister($schoolClass, $register);
            Gate::forUser($actor)->authorize('update', $register);
            $records = $register->records()->whereIn('id', array_keys($entries))->lockForUpdate()->get()->keyBy('id');
            if ($records->count() !== count($entries)) {
                throw ValidationException::withMessages(['entries' => 'Every selected record must belong to this register.']);
            }
            $changed = false;
            foreach ($entries as $id => $entry) {
                $record = $records[$id];
                $status = $entry['status'];
                $note = $entry['note'] ?? null;
                if ($record->status === $status && $record->note === $note) {
                    continue;
                }

                $record->update([
                    'status' => $status,
                    'note' => $note,
                    'marked_at' => $status === null ? null : now(),
                    'marked_by' => $status === null ? null : $actor->id,
                ]);
                $changed = true;
            }
            if ($changed) {
                $register->update(['reviewed_at' => null, 'reviewed_by' => null]);
            }
        });
    }

    public function review(User $actor, SchoolClass $schoolClass, ClassAttendanceRegister $register): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($register): void {
            $register = $this->lockedRegister($schoolClass, $register);
            Gate::forUser($actor)->authorize('update', $register);
            $this->requireComplete($register);
            $register->update(['reviewed_at' => now(), 'reviewed_by' => $actor->id]);
        });
    }

    public function finalize(User $actor, SchoolClass $schoolClass, ClassAttendanceRegister $register): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($register): void {
            $register = $this->lockedRegister($schoolClass, $register);
            Gate::forUser($actor)->authorize('update', $register);
            $this->requireComplete($register);
            if ($register->reviewed_at === null) {
                throw ValidationException::withMessages(['review' => 'Review the completed roster before finalizing.']);
            }
            $register->update(['finalized_at' => now(), 'finalized_by' => $actor->id]);
        });
    }

    public function correct(User $actor, SchoolClass $schoolClass, ClassAttendanceRegister $register, ClassAttendanceRecord $record, string $status, ?string $note, string $reason): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($register, $record, $status, $note, $reason): void {
            $register = $this->lockedRegister($schoolClass, $register);
            Gate::forUser($actor)->authorize('correct', $register);
            $record = $register->records()->lockForUpdate()->findOrFail($record->id);
            if ($record->status === $status && $record->note === $note) {
                throw ValidationException::withMessages(['status' => 'Change the status or note to record a correction.']);
            }
            $record->corrections()->create([
                'previous_status' => $record->status, 'new_status' => $status,
                'previous_note' => $record->note, 'new_note' => $note,
                'reason' => $reason, 'corrected_by' => $actor->id, 'corrected_at' => now(),
            ]);
            $record->update(['status' => $status, 'note' => $note, 'marked_at' => now(), 'marked_by' => $actor->id]);
        });
    }

    private function lockedRegister(SchoolClass $schoolClass, ClassAttendanceRegister $register): ClassAttendanceRegister
    {
        return $schoolClass->attendanceRegisters()->lockForUpdate()->findOrFail($register->id);
    }

    private function requireComplete(ClassAttendanceRegister $register): void
    {
        if (! $register->records()->exists() || $register->records()->whereNull('status')->exists()) {
            throw ValidationException::withMessages(['review' => 'Mark every student before reviewing or finalizing.']);
        }
    }
}
