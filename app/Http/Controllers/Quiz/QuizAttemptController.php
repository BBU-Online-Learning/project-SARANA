<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\SaveQuizAnswersRequest;
use App\Models\QuizAnswer;
use App\Models\QuizAssignment;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Notifications\ActivityNotification;
use App\Services\Quiz\QuizGradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuizAttemptController extends Controller
{
    public function start(SchoolClass $schoolClass, QuizAssignment $assignment): RedirectResponse
    {
        $this->ensureClass($schoolClass, $assignment);
        Gate::authorize('start', $assignment);
        $user = request()->user();
        $existing = $assignment->attempts()->where('user_id', $user->id)->where('status', 'in_progress')->first();
        if ($existing) {
            return redirect()->route('classes.quiz-attempts.show', [$schoolClass, $existing]);
        }
        $attemptNumber = $assignment->attempts()->where('user_id', $user->id)->count() + 1;
        if ($attemptNumber > $assignment->attempt_limit) {
            throw ValidationException::withMessages(['attempt' => 'You have used all available attempts.']);
        }
        $deadline = $assignment->time_limit_minutes ? now()->addMinutes($assignment->time_limit_minutes) : null;
        if ($deadline === null || $deadline->gt($assignment->due_at)) {
            $deadline = $assignment->due_at->copy();
        }
        $attempt = $assignment->attempts()->create(['user_id' => $user->id, 'attempt_number' => $attemptNumber, 'status' => 'in_progress', 'started_at' => now(), 'deadline_at' => $deadline, 'total_points' => $assignment->quiz->totalPoints()]);

        return redirect()->route('classes.quiz-attempts.show', [$schoolClass, $attempt]);
    }

    public function show(SchoolClass $schoolClass, QuizAttempt $attempt): View|RedirectResponse
    {
        $this->ensureAttemptClass($schoolClass, $attempt);
        Gate::authorize('view', $attempt);
        if ($attempt->status !== 'in_progress') {
            return redirect()->route('classes.quiz-attempts.result', [$schoolClass, $attempt]);
        }
        if ($attempt->isExpired()) {
            $this->submitAttempt($attempt, app(QuizGradingService::class));

            return redirect()->route('classes.quiz-attempts.result', [$schoolClass, $attempt])->with('warning', 'The time limit ended and your saved answers were submitted.');
        }
        $attempt->load('assignment.quiz.questions.options', 'answers');
        $answers = $attempt->answers->keyBy('quiz_question_id');

        return view('quizzes.attempt', compact('schoolClass', 'attempt', 'answers'));
    }

    public function save(SaveQuizAnswersRequest $request, SchoolClass $schoolClass, QuizAttempt $attempt): JsonResponse
    {
        $this->ensureAttemptClass($schoolClass, $attempt);
        Gate::authorize('update', $attempt);
        DB::transaction(function () use ($attempt, $request): void {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless($locked->status === 'in_progress', 409, 'This quiz has already been submitted.');
            abort_if($locked->isExpired(), 409, 'The quiz time limit has ended.');
            $this->persistAnswers($locked, $request->validated('answers', []));
        });

        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function submit(SaveQuizAnswersRequest $request, SchoolClass $schoolClass, QuizAttempt $attempt, QuizGradingService $grading): RedirectResponse
    {
        $this->ensureAttemptClass($schoolClass, $attempt);
        Gate::authorize('update', $attempt);
        $expired = $this->submitAttempt($attempt, $grading, $request->validated('answers', []));

        return redirect()->route('classes.quiz-attempts.result', [$schoolClass, $attempt])
            ->with($expired ? 'warning' : 'success', $expired
                ? 'The time limit ended and your saved answers were submitted.'
                : 'Quiz submitted successfully.');
    }

    public function result(SchoolClass $schoolClass, QuizAttempt $attempt): View
    {
        $this->ensureAttemptClass($schoolClass, $attempt);
        Gate::authorize('view', $attempt);
        $attempt->load('assignment.quiz.questions.options', 'answers.question.options', 'student');
        $isTeacher = request()->user()->id !== $attempt->user_id;
        $released = $isTeacher || ($attempt->status === 'graded' && $attempt->assignment->resultsAreReleased());

        return view('quizzes.result', compact('schoolClass', 'attempt', 'released', 'isTeacher'));
    }

    private function persistAnswers(QuizAttempt $attempt, array $payload): void
    {
        $questions = $attempt->assignment->quiz->questions()->with('options')->get()->keyBy('id');
        foreach ($payload as $questionId => $value) {
            $question = $questions->get((int) $questionId);
            if (! $question) {
                continue;
            }
            $selected = collect($value['options'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
            if ($selected->diff($question->options->pluck('id'))->isNotEmpty()) {
                continue;
            }
            QuizAnswer::query()->updateOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id],
                ['answer_text' => $value['text'] ?? null, 'selected_option_ids' => $selected->all() ?: null, 'is_correct' => null, 'points_awarded' => null]
            );
        }
    }

    private function submitAttempt(QuizAttempt $attempt, QuizGradingService $grading, array $answers = []): bool
    {
        $result = DB::transaction(function () use ($attempt, $grading, $answers): array {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status !== 'in_progress') {
                return ['expired' => false, 'pending_review' => false];
            }
            $expired = $locked->isExpired();
            if (! $expired) {
                $this->persistAnswers($locked, $answers);
            }
            foreach ($locked->assignment->quiz->questions as $question) {
                $locked->answers()->firstOrCreate(['quiz_question_id' => $question->id]);
            }
            $locked->update(['status' => 'submitted', 'submitted_at' => now()]);
            $grading->grade($locked);

            return ['expired' => $expired, 'pending_review' => $locked->fresh()->status === 'pending_review'];
        });

        if ($result['pending_review']) {
            $assignment = $attempt->assignment;
            $assignment->assigner?->notify(new ActivityNotification(
                'quiz',
                'Quiz needs grading',
                $attempt->student->name.' submitted '.$assignment->quiz->title.'.',
                route('classes.quiz-assignments.results.show', [$assignment->schoolClass, $assignment, $attempt], false),
            ));
        }

        return $result['expired'];
    }

    private function ensureClass(SchoolClass $schoolClass, QuizAssignment $assignment): void
    {
        abort_unless($assignment->school_class_id === $schoolClass->id, 404);
    }

    private function ensureAttemptClass(SchoolClass $schoolClass, QuizAttempt $attempt): void
    {
        abort_unless($attempt->assignment->school_class_id === $schoolClass->id, 404);
    }
}
