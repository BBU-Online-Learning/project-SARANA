<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\StoreQuizQuestionRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class QuizQuestionController extends Controller
{
    public function store(StoreQuizQuestionRequest $request, SchoolClass $schoolClass, Quiz $quiz): RedirectResponse
    {
        $this->authorizeQuiz($schoolClass, $quiz);
        DB::transaction(function () use ($request, $quiz): void {
            $question = $quiz->questions()->create($this->attributes($request) + ['position' => ((int) $quiz->questions()->max('position')) + 1]);
            $this->syncOptions($question, $request);
        });

        return back()->with('success', 'Question added.');
    }

    public function update(StoreQuizQuestionRequest $request, SchoolClass $schoolClass, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->authorizeQuestion($schoolClass, $quiz, $question);
        DB::transaction(function () use ($request, $question): void {
            $question->update($this->attributes($request));
            $question->options()->delete();
            $this->syncOptions($question, $request);
        });

        return back()->with('success', 'Question updated.');
    }

    public function duplicate(SchoolClass $schoolClass, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->authorizeQuestion($schoolClass, $quiz, $question);
        DB::transaction(function () use ($quiz, $question): void {
            $copy = $question->replicate(['position']);
            $copy->position = ((int) $quiz->questions()->max('position')) + 1;
            $copy->save();
            foreach ($question->options as $option) {
                $copy->options()->create($option->only(['text', 'is_correct', 'position']));
            }
        });

        return back()->with('success', 'Question duplicated.');
    }

    public function move(SchoolClass $schoolClass, Quiz $quiz, QuizQuestion $question, string $direction): RedirectResponse
    {
        $this->authorizeQuestion($schoolClass, $quiz, $question);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $swap = $quiz->questions()->where('position', $operator, $question->position)->orderBy('position', $order)->first();
        if ($swap) {
            DB::transaction(function () use ($question, $swap): void {
                [$questionPosition, $swapPosition] = [$question->position, $swap->position];
                $question->update(['position' => $swapPosition]);
                $swap->update(['position' => $questionPosition]);
            });
        }

        return back();
    }

    public function destroy(SchoolClass $schoolClass, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->authorizeQuestion($schoolClass, $quiz, $question);
        $question->delete();

        return back()->with('success', 'Question removed.');
    }

    private function attributes(StoreQuizQuestionRequest $request): array
    {
        $accepted = collect(preg_split('/\R/u', $request->input('accepted_answers', '')) ?: [])->map(fn (string $answer): string => trim($answer))->filter()->values()->all();

        return $request->safe()->only(['type', 'prompt', 'points', 'grading_mode', 'explanation']) + ['accepted_answers' => $accepted ?: null, 'case_sensitive' => $request->boolean('case_sensitive')];
    }

    private function syncOptions(QuizQuestion $question, StoreQuizQuestionRequest $request): void
    {
        if ($question->type === 'true_false') {
            $correct = $request->input('correct_options.0', '1') === '1';
            $question->options()->createMany([['text' => 'True', 'is_correct' => $correct, 'position' => 1], ['text' => 'False', 'is_correct' => ! $correct, 'position' => 2]]);

            return;
        }
        if ($question->type === 'short_answer') {
            return;
        }
        $correct = collect($request->input('correct_options', []))->map(fn ($index): int => (int) $index);
        foreach ($request->input('options', []) as $index => $option) {
            if (blank($option['text'] ?? null)) {
                continue;
            }
            $question->options()->create(['text' => $option['text'], 'is_correct' => $correct->contains((int) $index), 'position' => $index + 1]);
        }
    }

    private function authorizeQuiz(SchoolClass $schoolClass, Quiz $quiz): void
    {
        abort_unless($quiz->school_class_id === $schoolClass->id, 404);
        Gate::authorize('update', $quiz);
    }

    private function authorizeQuestion(SchoolClass $schoolClass, Quiz $quiz, QuizQuestion $question): void
    {
        $this->authorizeQuiz($schoolClass, $quiz);
        abort_unless($question->quiz_id === $quiz->id, 404);
    }
}
