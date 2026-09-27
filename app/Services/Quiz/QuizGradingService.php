<?php

namespace App\Services\Quiz;

use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\DB;

class QuizGradingService
{
    public function grade(QuizAttempt $attempt): QuizAttempt
    {
        return DB::transaction(function () use ($attempt): QuizAttempt {
            $attempt = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $attempt->load('answers.question.options', 'assignment.quiz.questions');
            $hasManualAnswers = false;

            foreach ($attempt->answers as $answer) {
                $question = $answer->question;
                if ($question->grading_mode === 'manual') {
                    $hasManualAnswers = true;

                    continue;
                }

                $correct = $this->isCorrect($question, $answer);
                $answer->update(['is_correct' => $correct, 'points_awarded' => $correct ? $question->points : 0]);
            }

            return $this->finalize($attempt, $hasManualAnswers);
        });
    }

    public function finalize(QuizAttempt $attempt, ?bool $hasPendingManual = null): QuizAttempt
    {
        $attempt->loadMissing('answers.question', 'assignment.quiz.questions');
        $total = (float) $attempt->assignment->quiz->questions->sum('points');
        $hasPendingManual ??= $attempt->answers->contains(fn (QuizAnswer $answer): bool => $answer->points_awarded === null);
        $earned = (float) $attempt->answers->sum(fn (QuizAnswer $answer): float => (float) ($answer->points_awarded ?? 0));
        $attempt->update([
            'total_points' => $total,
            'earned_points' => $earned,
            'percentage' => $total > 0 ? round(($earned / $total) * 100, 2) : 0,
            'status' => $hasPendingManual ? 'pending_review' : 'graded',
            'graded_at' => $hasPendingManual ? null : now(),
        ]);

        return $attempt->refresh();
    }

    private function isCorrect(QuizQuestion $question, QuizAnswer $answer): bool
    {
        if ($question->type === 'short_answer') {
            $submitted = $this->normalize($answer->answer_text ?? '', $question->case_sensitive);

            return collect($question->accepted_answers ?? [])->contains(
                fn (string $accepted): bool => $this->normalize($accepted, $question->case_sensitive) === $submitted
            );
        }

        $selected = collect($answer->selected_option_ids ?? [])->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $correct = $question->options->where('is_correct', true)->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

        return $selected === $correct;
    }

    private function normalize(string $answer, bool $caseSensitive): string
    {
        $answer = preg_replace('/\s+/u', ' ', trim($answer)) ?? trim($answer);

        return $caseSensitive ? $answer : mb_strtolower($answer);
    }
}
