<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\GradeQuizAttemptRequest;
use App\Models\QuizAssignment;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Notifications\ActivityNotification;
use App\Services\Quiz\QuizGradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuizResultController extends Controller
{
    public function index(SchoolClass $schoolClass, QuizAssignment $assignment): View
    {
        $this->authorizeAssignment($schoolClass, $assignment);
        $assignment->load(['quiz.questions', 'students', 'attempts.student']);
        $submitted = $assignment->attempts->whereIn('status', ['submitted', 'pending_review', 'graded']);
        $submittedStudentCount = $submitted->pluck('user_id')->unique()->count();
        $average = $submitted->whereNotNull('percentage')->avg('percentage');
        $statistics = $assignment->quiz->questions->map(function ($question) use ($submitted): array {
            $answers = \App\Models\QuizAnswer::query()->whereIn('quiz_attempt_id', $submitted->pluck('id'))->where('quiz_question_id', $question->id)->get();
            $graded = $answers->whereNotNull('is_correct');
            $correct = $graded->where('is_correct', true)->count();

            return ['question' => $question, 'correct' => $correct, 'incorrect' => $graded->count() - $correct, 'rate' => $graded->count() ? round($correct / $graded->count() * 100, 1) : null];
        });

        return view('quizzes.results', compact('schoolClass', 'assignment', 'submitted', 'submittedStudentCount', 'average', 'statistics'));
    }

    public function show(SchoolClass $schoolClass, QuizAssignment $assignment, QuizAttempt $attempt): View
    {
        $this->authorizeAssignment($schoolClass, $assignment);
        abort_unless($attempt->quiz_assignment_id === $assignment->id, 404);
        abort_if($attempt->status === 'in_progress', 404);
        $attempt->load('student', 'answers.question.options', 'assignment.quiz.questions');

        return view('quizzes.grade', compact('schoolClass', 'assignment', 'attempt'));
    }

    public function update(GradeQuizAttemptRequest $request, SchoolClass $schoolClass, QuizAssignment $assignment, QuizAttempt $attempt, QuizGradingService $grading): RedirectResponse
    {
        $this->authorizeAssignment($schoolClass, $assignment);
        Gate::authorize('grade', $attempt);
        abort_unless($attempt->quiz_assignment_id === $assignment->id, 404);
        $wasGraded = false;
        DB::transaction(function () use ($request, $attempt, $grading, &$wasGraded): void {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_if($locked->status === 'in_progress', 409, 'This quiz has not been submitted.');
            $wasGraded = $locked->status === 'graded';
            $locked->load('answers.question');
            foreach ($request->validated('grades') as $answerId => $grade) {
                $answer = $locked->answers->firstWhere('id', (int) $answerId);
                if (! $answer) {
                    continue;
                }
                if ((float) $grade['points'] > (float) $answer->question->points) {
                    throw ValidationException::withMessages(["grades.$answerId.points" => 'Points cannot exceed the question value.']);
                }
                $answer->update(['points_awarded' => $grade['points'], 'is_correct' => (float) $grade['points'] >= (float) $answer->question->points, 'feedback' => $grade['feedback'] ?? null]);
            }
            $locked->update(['feedback' => $request->validated('feedback')]);
            $grading->finalize($locked);
        });

        if (! $wasGraded && $assignment->resultsAreReleased()) {
            $attempt->student?->notify(new ActivityNotification(
                'quiz',
                'Quiz grade available',
                'Your result for '.$assignment->quiz->title.' is ready.',
                route('classes.quiz-attempts.result', [$schoolClass, $attempt], false),
            ));
        }

        return redirect()->route('classes.quiz-assignments.results', [$schoolClass, $assignment])->with('success', 'Grade saved.');
    }

    private function authorizeAssignment(SchoolClass $schoolClass, QuizAssignment $assignment): void
    {
        abort_unless($assignment->school_class_id === $schoolClass->id, 404);
        Gate::authorize('manage', $assignment);
    }
}
