<?php

namespace App\Http\Controllers;

use App\Http\Requests\Coursework\GradeCourseworkSubmissionRequest;
use App\Http\Requests\Coursework\SaveCourseworkDraftRequest;
use App\Models\CourseworkAssignment;
use App\Models\CourseworkSubmission;
use App\Models\SchoolClass;
use App\Services\ClassAccessService;
use App\Services\CourseworkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourseworkSubmissionController extends Controller
{
    public function __construct(private CourseworkService $coursework, private ClassAccessService $access) {}

    public function saveDraft(SaveCourseworkDraftRequest $request, SchoolClass $schoolClass, CourseworkAssignment $assignment): RedirectResponse
    {
        $this->ensureClass($schoolClass, $assignment);
        $this->coursework->saveDraft($request->user(), $schoolClass, $assignment, $request->validated('body'), $request->validated('attachments', []));

        return back()->with('success', 'Draft saved.');
    }

    public function submit(SchoolClass $schoolClass, CourseworkAssignment $assignment): RedirectResponse
    {
        $this->ensureClass($schoolClass, $assignment);
        $this->coursework->submit(Auth::user(), $schoolClass, $assignment);

        return redirect()->route('classes.coursework.assignments.show', [$schoolClass, $assignment])->with('success', 'Coursework submitted.');
    }

    public function resubmit(SchoolClass $schoolClass, CourseworkAssignment $assignment): RedirectResponse
    {
        $this->ensureClass($schoolClass, $assignment);
        $this->coursework->resubmit(Auth::user(), $schoolClass, $assignment);

        return redirect()->route('classes.coursework.assignments.show', [$schoolClass, $assignment])->with('success', 'New draft started. Your submitted revision remains available.');
    }

    public function show(SchoolClass $schoolClass, CourseworkAssignment $assignment, CourseworkSubmission $submission): View
    {
        $this->ensureClass($schoolClass, $assignment);
        abort_unless((int) $submission->coursework_assignment_id === (int) $assignment->id, 404);
        Gate::authorize('view', $submission);
        $isTeacher = $this->access->teachingRole(Auth::user(), $schoolClass) !== null;
        $submission->load(['student', 'grades.grader', 'grades.revision']);
        $revisions = $submission->revisions()->with('attachments')
            ->when($isTeacher, fn ($query) => $query->where('status', 'submitted'))
            ->orderByDesc('revision_number')->get();

        return view('coursework.submission', compact('schoolClass', 'assignment', 'submission', 'revisions', 'isTeacher'));
    }

    public function grade(GradeCourseworkSubmissionRequest $request, SchoolClass $schoolClass, CourseworkAssignment $assignment, CourseworkSubmission $submission): RedirectResponse
    {
        $this->ensureClass($schoolClass, $assignment);
        abort_unless((int) $submission->coursework_assignment_id === (int) $assignment->id, 404);
        $this->coursework->grade($request->user(), $schoolClass, $assignment, $submission, $request->validated());

        return redirect()->route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission])->with('success', 'Grade recorded.');
    }

    private function ensureClass(SchoolClass $schoolClass, CourseworkAssignment $assignment): void
    {
        abort_unless((int) $assignment->school_class_id === (int) $schoolClass->id, 404);
    }
}
