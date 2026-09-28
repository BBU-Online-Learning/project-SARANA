<?php

namespace App\Services;

use App\Models\CourseworkAssignment;
use App\Models\CourseworkAttachment;
use App\Models\CourseworkGrade;
use App\Models\CourseworkRevision;
use App\Models\CourseworkSubmission;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CourseworkService
{
    public function __construct(private ClassManagementService $classes) {}

    /** @param array<int, UploadedFile> $files */
    public function saveDraft(User $student, SchoolClass $schoolClass, CourseworkAssignment $assignment, ?string $body, array $files): CourseworkSubmission
    {
        $storedPaths = [];
        try {
            return $this->classes->withClass($student, $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($assignment, $body, $files, &$storedPaths): CourseworkSubmission {
                $lockedAssignment = $this->assignment($lockedClass, $assignment);
                Gate::forUser($actor)->authorize('create', [CourseworkSubmission::class, $lockedAssignment]);
                $submission = $lockedAssignment->submissions()->where('student_id', $actor->id)->lockForUpdate()->first();
                if (! $submission) {
                    $submission = $lockedAssignment->submissions()->create([
                        'student_id' => $actor->id,
                        'status' => 'draft',
                        'latest_revision_number' => 0,
                    ]);
                }
                Gate::forUser($actor)->authorize('saveDraft', $submission);
                $draft = $submission->revisions()->where('draft_slot', 1)->lockForUpdate()->first();
                if (! $draft) {
                    $draft = $submission->revisions()->create([
                        'revision_number' => $submission->latest_revision_number + 1,
                        'status' => 'draft',
                        'draft_slot' => 1,
                    ]);
                    $submission->update(['latest_revision_number' => $draft->revision_number]);
                }

                $existingCount = $draft->attachments()->count();
                $existingBytes = (int) $draft->attachments()->sum('size_bytes');
                $newBytes = array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $files));
                if ($existingCount + count($files) > 5 || $existingBytes + $newBytes > 25 * 1024 * 1024) {
                    throw ValidationException::withMessages(['attachments' => 'A draft may have at most five files and 25 MB total.']);
                }
                $draft->update(['body' => $body]);
                foreach ($files as $file) {
                    $path = $file->store('coursework/'.$draft->id, 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['attachments' => 'The file could not be stored. Try again.']);
                    }
                    $storedPaths[] = $path;
                    $draft->attachments()->create([
                        'uploaded_by' => $actor->id,
                        'path' => $path,
                        'original_name' => $this->safeFilename($file->getClientOriginalName()),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                    ]);
                }

                return $submission->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    public function submit(User $student, SchoolClass $schoolClass, CourseworkAssignment $assignment): CourseworkSubmission
    {
        $submission = $this->classes->withClass($student, $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($assignment): CourseworkSubmission {
            $lockedAssignment = $this->assignment($lockedClass, $assignment);
            $submission = $lockedAssignment->submissions()->where('student_id', $actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('submit', $submission);
            $draft = $submission->revisions()->where('draft_slot', 1)->lockForUpdate()->firstOrFail();
            if (trim((string) $draft->body) === '' && ! $draft->attachments()->exists()) {
                throw ValidationException::withMessages(['submission' => 'Add a written response or an attachment before submitting.']);
            }

            $submittedAt = now();
            $draft->update([
                'status' => 'submitted',
                'draft_slot' => null,
                'submitted_at' => $submittedAt,
                'is_late' => $lockedAssignment->due_at ? $submittedAt->greaterThan($lockedAssignment->due_at) : false,
            ]);
            $submission->update(['status' => 'submitted', 'last_submitted_at' => $submittedAt]);

            return $submission->refresh();
        });

        $this->notifyTeachers($schoolClass, $assignment, 'Coursework submitted', $student->name.' submitted '.$assignment->title.'.');

        return $submission;
    }

    public function resubmit(User $student, SchoolClass $schoolClass, CourseworkAssignment $assignment): CourseworkSubmission
    {
        return $this->classes->withClass($student, $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($assignment): CourseworkSubmission {
            $lockedAssignment = $this->assignment($lockedClass, $assignment);
            $submission = $lockedAssignment->submissions()->where('student_id', $actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('resubmit', $submission);
            $previous = $submission->revisions()->where('status', 'submitted')->orderByDesc('revision_number')->firstOrFail();
            $submission->revisions()->create([
                'revision_number' => $submission->latest_revision_number + 1,
                'status' => 'draft',
                'draft_slot' => 1,
                'body' => $previous->body,
            ]);
            $submission->update(['status' => 'draft', 'latest_revision_number' => $submission->latest_revision_number + 1]);

            return $submission->refresh();
        });
    }

    /** @param array{points_awarded: int|float|string, feedback?: string|null, change_reason?: string|null} $data */
    public function grade(User $teacher, SchoolClass $schoolClass, CourseworkAssignment $assignment, CourseworkSubmission $submission, array $data): CourseworkGrade
    {
        $grade = $this->classes->withClass($teacher, $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($assignment, $submission, $data): CourseworkGrade {
            $lockedAssignment = $this->assignment($lockedClass, $assignment);
            $lockedSubmission = $lockedAssignment->submissions()->lockForUpdate()->findOrFail($submission->id);
            Gate::forUser($actor)->authorize('grade', $lockedSubmission);
            $revision = $lockedSubmission->revisions()->where('status', 'submitted')->orderByDesc('revision_number')->firstOrFail();
            $previous = $lockedSubmission->grades()->first();
            if ($previous && trim((string) ($data['change_reason'] ?? '')) === '') {
                throw ValidationException::withMessages(['change_reason' => 'Explain why this grade changed.']);
            }
            if ((float) $data['points_awarded'] < 0 || (float) $data['points_awarded'] > (float) $lockedAssignment->max_points) {
                throw ValidationException::withMessages(['points_awarded' => 'Points must be within the assignment maximum.']);
            }

            return $lockedSubmission->grades()->create([
                'coursework_revision_id' => $revision->id,
                'graded_by' => $actor->id,
                'points_awarded' => $data['points_awarded'],
                'max_points_snapshot' => $lockedAssignment->max_points,
                'feedback' => $data['feedback'] ?? null,
                'change_reason' => $previous ? trim($data['change_reason']) : null,
            ]);
        });

        if ($schoolClass->memberRecords()->where('user_id', $submission->student_id)->where('role', 'student')->exists()) {
            User::query()->find($submission->student_id)?->notify(new ActivityNotification(
                'coursework', 'Coursework graded', 'Feedback is available for '.$assignment->title.'.',
                route('classes.coursework.assignments.show', [$schoolClass, $assignment], false),
            ));
        }

        return $grade;
    }

    public function removeDraftAttachment(User $student, SchoolClass $schoolClass, CourseworkAssignment $assignment, CourseworkSubmission $submission, CourseworkRevision $revision, CourseworkAttachment $attachment): void
    {
        $path = $this->classes->withClass($student, $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($assignment, $submission, $revision, $attachment): string {
            $lockedAssignment = $this->assignment($lockedClass, $assignment);
            $lockedSubmission = $lockedAssignment->submissions()->lockForUpdate()->findOrFail($submission->id);
            Gate::forUser($actor)->authorize('saveDraft', $lockedSubmission);
            $lockedRevision = $lockedSubmission->revisions()->where('draft_slot', 1)->findOrFail($revision->id);
            $lockedAttachment = $lockedRevision->attachments()->findOrFail($attachment->id);
            $path = $lockedAttachment->path;
            $lockedAttachment->delete();

            return $path;
        });

        Storage::disk('local')->delete($path);
    }

    private function assignment(SchoolClass $schoolClass, CourseworkAssignment $assignment): CourseworkAssignment
    {
        return $schoolClass->courseworkAssignments()->lockForUpdate()->findOrFail($assignment->id);
    }

    private function safeFilename(string $name): string
    {
        $safe = preg_replace('/[\\\\\/\x00-\x1F\x7F]/u', '_', $name) ?: 'attachment';

        return mb_substr($safe, 0, 180);
    }

    private function notifyTeachers(SchoolClass $schoolClass, CourseworkAssignment $assignment, string $title, string $body): void
    {
        $teacherIds = $schoolClass->memberRecords()->whereIn('role', ['owner', 'teacher'])->pluck('user_id');
        User::query()->whereIn('id', $teacherIds)->get()->each(fn (User $teacher) => $teacher->notify(new ActivityNotification(
            'coursework', $title, $body,
            route('classes.coursework.assignments.show', [$schoolClass, $assignment], false),
        )));
    }
}
