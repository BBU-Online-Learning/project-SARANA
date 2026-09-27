@extends('layouts.app')
@section('title', 'Quiz Result')
@section('styles')
    <link rel="stylesheet" href="{{ asset('css/quiz.css') }}">
@endsection
@section('content')
<div class="page-container quiz-page">
    <div class="quiz-hero">
        <div><h1>{{ $attempt->assignment->quiz->title }}</h1><p>Submitted {{ $attempt->submitted_at?->format('M j, Y g:i A') }}</p></div>
        <a class="btn btn-light" href="{{ route('classes.assessments.index', $schoolClass) }}">Assessments</a>
    </div>
    <div class="quiz-results-summary">
        <div class="quiz-stat"><span>Status</span><strong>{{ ucwords(str_replace('_', ' ', $attempt->status)) }}</strong></div>
        <div class="quiz-stat"><span>Score</span><strong>{{ $released && $attempt->earned_points !== null ? $attempt->earned_points.'/'.$attempt->total_points : 'Pending' }}</strong></div>
        <div class="quiz-stat"><span>Percentage</span><strong>{{ $released && $attempt->percentage !== null ? $attempt->percentage.'%' : '—' }}</strong></div>
        <div class="quiz-stat"><span>Attempt</span><strong>{{ $attempt->attempt_number }}</strong></div>
    </div>
    @if(! $released)
        <div class="alert alert-info">Your answers were submitted. Results will appear when your teacher releases them.</div>
    @else
        <div class="quiz-panel">
            @foreach($attempt->assignment->quiz->questions as $question)
                @php($answer = $attempt->answers->firstWhere('quiz_question_id', $question->id))
                <article class="quiz-question-card">
                    <div class="quiz-question-heading"><h3>{{ $loop->iteration }}. {{ $question->prompt }}</h3><strong>{{ $answer?->points_awarded ?? 0 }} / {{ $question->points }}</strong></div>
                    <div class="mt-2"><strong>Your answer:</strong>
                        @if($question->type === 'short_answer')
                            {{ $answer?->answer_text ?: 'No answer' }}
                        @else
                            {{ $question->options->whereIn('id', $answer?->selected_option_ids ?? [])->pluck('text')->join(', ') ?: 'No answer' }}
                        @endif
                    </div>
                    @if($attempt->assignment->show_correct_answers)
                        <div class="mt-2 text-success"><strong>Correct answer:</strong> {{ $question->type === 'short_answer' ? collect($question->accepted_answers)->join(', ') : $question->options->where('is_correct', true)->pluck('text')->join(', ') }}</div>
                        @if($question->explanation)<p class="mt-2 text-muted">{{ $question->explanation }}</p>@endif
                    @endif
                    @if($answer?->feedback)<p class="mt-2"><strong>Feedback:</strong> {{ $answer->feedback }}</p>@endif
                </article>
            @endforeach
            @if($attempt->feedback)<div class="alert alert-light"><strong>Teacher feedback:</strong> {{ $attempt->feedback }}</div>@endif
        </div>
    @endif
</div>
@endsection
