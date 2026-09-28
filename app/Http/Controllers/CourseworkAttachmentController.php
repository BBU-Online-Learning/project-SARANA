<?php

namespace App\Http\Controllers;

use App\Models\CourseworkAssignment;
use App\Models\CourseworkAttachment;
use App\Models\CourseworkRevision;
use App\Models\CourseworkSubmission;
use App\Models\SchoolClass;
use App\Services\CourseworkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseworkAttachmentController extends Controller
{
    public function __construct(private CourseworkService $coursework) {}

    public function download(SchoolClass $schoolClass, CourseworkAssignment $assignment, CourseworkSubmission $submission, CourseworkRevision $revision, CourseworkAttachment $attachment): StreamedResponse
    {
        $this->ensureRelationship($schoolClass, $assignment, $submission, $revision, $attachment);
        Gate::authorize('download', $attachment);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(SchoolClass $schoolClass, CourseworkAssignment $assignment, CourseworkSubmission $submission, CourseworkRevision $revision, CourseworkAttachment $attachment): RedirectResponse
    {
        $this->ensureRelationship($schoolClass, $assignment, $submission, $revision, $attachment);
        $this->coursework->removeDraftAttachment(Auth::user(), $schoolClass, $assignment, $submission, $revision, $attachment);

        return back()->with('success', 'Draft attachment removed.');
    }

    private function ensureRelationship(SchoolClass $schoolClass, CourseworkAssignment $assignment, CourseworkSubmission $submission, CourseworkRevision $revision, CourseworkAttachment $attachment): void
    {
        abort_unless((int) $assignment->school_class_id === (int) $schoolClass->id
            && (int) $submission->coursework_assignment_id === (int) $assignment->id
            && (int) $revision->coursework_submission_id === (int) $submission->id
            && (int) $attachment->coursework_revision_id === (int) $revision->id
            && str_starts_with($attachment->path, 'coursework/'.$revision->id.'/'), 404);
    }
}
