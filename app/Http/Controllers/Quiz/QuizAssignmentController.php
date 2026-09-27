<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\StoreQuizAssignmentRequest;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Notifications\ActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuizAssignmentController extends Controller
{
    public function create(SchoolClass $schoolClass, Quiz $quiz): View
    {
        $this->authorizeQuiz($schoolClass, $quiz);
        $students = $schoolClass->members()->wherePivot('role', 'student')->orderBy('name')->get();

        return view('quizzes.assign', compact('schoolClass', 'quiz', 'students'));
    }

    public function store(StoreQuizAssignmentRequest $request, SchoolClass $schoolClass, Quiz $quiz): RedirectResponse
    {
        $this->authorizeQuiz($schoolClass, $quiz);
        $classStudentIds = $schoolClass->members()->wherePivot('role', 'student')->pluck('users.id');
        $requestedIds = collect($request->validated('student_ids', []))->map(fn ($id): int => (int) $id)->unique();
        $studentIds = $requestedIds->isEmpty() ? $classStudentIds : $requestedIds;
        if ($studentIds->diff($classStudentIds)->isNotEmpty() || $studentIds->isEmpty()) {
            throw ValidationException::withMessages(['student_ids' => 'Select at least one student from this class.']);
        }

        $assignment = DB::transaction(function () use ($request, $schoolClass, $quiz, $studentIds) {
            $assignment = $quiz->assignments()->create($request->safe()->only(['instructions', 'starts_at', 'due_at', 'attempt_limit', 'time_limit_minutes', 'results_release']) + [
                'school_class_id' => $schoolClass->id, 'assigned_by' => $request->user()->id,
                'status' => 'scheduled', 'show_correct_answers' => $request->boolean('show_correct_answers'),
            ]);
            $assignment->students()->attach($studentIds->all(), ['assigned_at' => now()]);

            return $assignment;
        });

        $assignment->students()->get()->each(fn ($student) => $student->notify(new ActivityNotification(
            'quiz',
            'New quiz: '.$quiz->title,
            'A quiz was assigned in '.$schoolClass->name.'.',
            route('classes.assessments.index', $schoolClass, false),
        )));

        return redirect()->route('classes.assessments.index', $schoolClass)->with('success', 'Quiz assigned to '.$studentIds->count().' student(s).');
    }

    public function release(SchoolClass $schoolClass, \App\Models\QuizAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->school_class_id === $schoolClass->id, 404);
        Gate::authorize('manage', $assignment);
        if ($assignment->results_released_at !== null) {
            return back()->with('success', 'Results are already available to students.');
        }
        $assignment->update(['results_released_at' => now()]);

        $assignment->students()->get()->each(fn ($student) => $student->notify(new ActivityNotification(
            'quiz',
            'Quiz results available',
            'Results for '.$assignment->quiz->title.' are ready to view.',
            route('classes.assessments.index', $schoolClass, false),
        )));

        return back()->with('success', 'Results released to students.');
    }

    private function authorizeQuiz(SchoolClass $schoolClass, Quiz $quiz): void
    {
        abort_unless($quiz->school_class_id === $schoolClass->id, 404);
        Gate::authorize('assign', $quiz);
    }
}
