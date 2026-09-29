<?php

namespace App\Http\Controllers;

use App\Http\Requests\Coursework\StoreCourseworkAssignmentRequest;
use App\Http\Requests\Coursework\UpdateCourseworkAssignmentRequest;
use App\Models\CourseworkAssignment;
use App\Models\ReportingPeriod;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Services\ClassAccessService;
use App\Services\ClassManagementService;
use App\Services\SubjectTeacherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourseworkAssignmentController extends Controller
{
    public function __construct(private ClassManagementService $classes, private ClassAccessService $access, private SubjectTeacherService $subjectTeachers) {}

    public function index(SchoolClass $schoolClass): View
    {
        Gate::authorize('viewAny', [CourseworkAssignment::class, $schoolClass]);
        $isTeacher = $this->access->teachingRole(Auth::user(), $schoolClass) !== null;
        $blockedSubjects = [];
        if ($isTeacher && $this->access->teachingRole(Auth::user(), $schoolClass) !== 'owner') {
            $restricted = $schoolClass->subjectTeacherAssignments()->where('active_slot', 1)->distinct()->pluck('subject_id');
            $assigned = $schoolClass->subjectTeacherAssignments()->where('user_id', Auth::id())
                ->where('active_slot', 1)->pluck('subject_id');
            $blockedSubjects = $restricted->diff($assigned)->all();
        }
        $assignments = $schoolClass->courseworkAssignments()->with(['creator', 'academicYear', 'subject', 'reportingPeriod'])
            ->when(! $isTeacher, fn ($query) => $query->whereIn('status', ['published', 'closed']))
            ->when($blockedSubjects !== [], fn ($query) => $query->where(function ($query) use ($blockedSubjects): void {
                $query->where('status', '!=', 'draft')->orWhereNull('subject_id')->orWhereNotIn('subject_id', $blockedSubjects);
            }))
            ->orderByDesc('id')->paginate(20);

        return view('coursework.index', compact('schoolClass', 'assignments', 'isTeacher'));
    }

    public function create(SchoolClass $schoolClass): View
    {
        Gate::authorize('create', [CourseworkAssignment::class, $schoolClass]);
        $schoolClass->load('subjects');
        $availableSubjects = $schoolClass->subjects->filter(fn ($subject): bool => $this->subjectTeachers->canManageCoursework(Auth::user(), $schoolClass, $subject->id));
        $reportingPeriods = ReportingPeriod::query()->where('academic_year_id', $schoolClass->academic_year_id)->orderBy('sequence')->get();
        $assignment = null;

        return view('coursework.form', compact('schoolClass', 'assignment', 'availableSubjects', 'reportingPeriods'));
    }

    public function store(StoreCourseworkAssignmentRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validated();
        $assignment = $this->classes->withClass($request->user(), $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($validated): CourseworkAssignment {
            Gate::forUser($actor)->authorize('create', [CourseworkAssignment::class, $lockedClass]);
            $this->ensureSubject($lockedClass, $validated['subject_id'] ?? null);
            $this->ensureReportingPeriod($lockedClass->academic_year_id, $validated['reporting_period_id'] ?? null);
            abort_unless($this->subjectTeachers->canManageCoursework($actor, $lockedClass, $validated['subject_id'] ?? null), 403);

            return $lockedClass->courseworkAssignments()->create($validated + [
                'created_by' => $actor->id,
                'academic_year_id' => $lockedClass->academic_year_id,
            ]);
        });

        return redirect()->route('classes.coursework.assignments.show', [$schoolClass, $assignment])->with('success', 'Coursework draft created.');
    }

    public function show(SchoolClass $schoolClass, CourseworkAssignment $assignment): View
    {
        $this->ensureClass($schoolClass, $assignment);
        Gate::authorize('view', $assignment);
        $assignment->load(['creator', 'academicYear', 'subject', 'reportingPeriod']);
        $isTeacher = $this->access->teachingRole(Auth::user(), $schoolClass) !== null;
        $canReviewSubmissions = $isTeacher && $this->subjectTeachers->canManageCoursework(Auth::user(), $schoolClass, $assignment->subject_id);
        $submissions = null;
        $ownSubmission = null;
        if ($canReviewSubmissions) {
            $submissions = $assignment->submissions()->with('student')
                ->whereHas('revisions', fn ($query) => $query->where('status', 'submitted'))
                ->orderByDesc('last_submitted_at')->paginate(20);
        } elseif (! $isTeacher) {
            $ownSubmission = $assignment->submissions()->where('student_id', Auth::id())
                ->with(['revisions.attachments', 'grades.grader', 'grades.revision'])->first();
        }

        return view('coursework.show', compact('schoolClass', 'assignment', 'isTeacher', 'canReviewSubmissions', 'submissions', 'ownSubmission'));
    }

    public function edit(SchoolClass $schoolClass, CourseworkAssignment $assignment): View
    {
        $this->ensureClass($schoolClass, $assignment);
        Gate::authorize('update', $assignment);
        $schoolClass->load('subjects');
        $availableSubjects = $schoolClass->subjects->filter(fn ($subject): bool => $this->subjectTeachers->canManageCoursework(Auth::user(), $schoolClass, $subject->id));
        $reportingPeriods = ReportingPeriod::query()->where('academic_year_id', $assignment->academic_year_id)->orderBy('sequence')->get();

        return view('coursework.form', compact('schoolClass', 'assignment', 'availableSubjects', 'reportingPeriods'));
    }

    public function update(UpdateCourseworkAssignmentRequest $request, SchoolClass $schoolClass, CourseworkAssignment $assignment): RedirectResponse
    {
        $validated = $request->validated();
        $this->classes->withClass($request->user(), $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($assignment, $validated): void {
            $locked = $lockedClass->courseworkAssignments()->lockForUpdate()->findOrFail($assignment->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $this->ensureSubject($lockedClass, $validated['subject_id'] ?? null);
            $this->ensureReportingPeriod($locked->academic_year_id, $validated['reporting_period_id'] ?? null);
            abort_unless($this->subjectTeachers->canManageCoursework($actor, $lockedClass, $validated['subject_id'] ?? null), 403);
            $locked->update($validated);
        });

        return redirect()->route('classes.coursework.assignments.show', [$schoolClass, $assignment])->with('success', 'Coursework draft updated.');
    }

    public function publish(SchoolClass $schoolClass, CourseworkAssignment $assignment): RedirectResponse
    {
        $this->ensureClass($schoolClass, $assignment);
        $this->classes->withClass(Auth::user(), $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($assignment): void {
            $locked = $lockedClass->courseworkAssignments()->lockForUpdate()->findOrFail($assignment->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $locked->update(['status' => 'published', 'published_at' => now()]);
        });

        $studentIds = $schoolClass->memberRecords()->where('role', 'student')->pluck('user_id');
        User::query()->whereIn('id', $studentIds)->get()->each(fn (User $student) => $student->notify(new ActivityNotification(
            'coursework', 'New coursework', $assignment->title.' is ready in '.$schoolClass->name.'.',
            route('classes.coursework.assignments.show', [$schoolClass, $assignment], false),
        )));

        return redirect()->route('classes.coursework.assignments.show', [$schoolClass, $assignment])->with('success', 'Coursework published.');
    }

    public function close(SchoolClass $schoolClass, CourseworkAssignment $assignment): RedirectResponse
    {
        $this->ensureClass($schoolClass, $assignment);
        $this->classes->withClass(Auth::user(), $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($assignment): void {
            $locked = $lockedClass->courseworkAssignments()->lockForUpdate()->findOrFail($assignment->id);
            Gate::forUser($actor)->authorize('close', $locked);
            $locked->update(['status' => 'closed', 'closed_at' => now()]);
        });

        return redirect()->route('classes.coursework.assignments.show', [$schoolClass, $assignment])->with('success', 'Coursework closed.');
    }

    private function ensureClass(SchoolClass $schoolClass, CourseworkAssignment $assignment): void
    {
        abort_unless((int) $assignment->school_class_id === (int) $schoolClass->id, 404);
    }

    private function ensureSubject(SchoolClass $schoolClass, ?int $subjectId): void
    {
        if ($subjectId !== null && ! $schoolClass->subjects()->whereKey($subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Choose a subject assigned to this class.']);
        }
    }

    private function ensureReportingPeriod(?int $academicYearId, ?int $reportingPeriodId): void
    {
        if ($reportingPeriodId !== null && ! ReportingPeriod::query()->whereKey($reportingPeriodId)
            ->where('academic_year_id', $academicYearId)->exists()) {
            throw ValidationException::withMessages(['reporting_period_id' => 'Choose a reporting period from this assignment academic year.']);
        }
    }
}
