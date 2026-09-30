@extends('layouts.app')
@section('bodyClass', 'workspace-full-page')
@section('title', $attempt->assignment->quiz->title)
@section('styles')<link rel="stylesheet" href="{{ asset('css/quiz.css') }}">@endsection
@section('content')
<div class="page-container quiz-page" data-quiz-attempt data-save-url="{{ route('classes.quiz-attempts.save', [$schoolClass, $attempt]) }}" data-deadline="{{ $attempt->deadline_at?->toIso8601String() }}">
<div class="quiz-hero"><div><h1>{{ $attempt->assignment->quiz->title }}</h1><p>{{ $attempt->assignment->instructions ?: $attempt->assignment->quiz->description }}</p></div><div>@if($attempt->deadline_at)<div class="quiz-timer" data-quiz-timer>--:--</div>@endif<div class="quiz-save-state mt-2 text-white" data-save-state>Answers save automatically</div></div></div>
<div class="quiz-progress"><span data-quiz-progress style="width:0"></span></div>
<form method="POST" action="{{ route('classes.quiz-attempts.submit', [$schoolClass, $attempt]) }}" data-attempt-form data-confirm-title="Submit quiz?" data-confirm-message="Your saved answers will be submitted for grading. You may not be able to change them afterward." data-confirm-label="Submit quiz">@csrf
@foreach($attempt->assignment->quiz->questions as $question)@php($answer = $answers->get($question->id))<article class="quiz-question-card" data-quiz-question><div class="quiz-question-heading"><h3>Question {{ $loop->iteration }} of {{ $loop->count }}</h3><strong>{{ $question->points }} pts</strong></div><p class="mt-3">{{ $question->prompt }}</p>
@if($question->type === 'short_answer')<textarea class="form-control" name="answers[{{ $question->id }}][text]" rows="4" data-answer>{{ $answer?->answer_text }}</textarea>
@else @foreach($question->options as $option)<label class="quiz-option"><input type="{{ $question->type === 'multiple_answer' ? 'checkbox' : 'radio' }}" name="answers[{{ $question->id }}][options][]" value="{{ $option->id }}" @checked(in_array($option->id, $answer?->selected_option_ids ?? [], true)) data-answer><span>{{ $option->text }}</span></label>@endforeach @endif
</article>@endforeach
<div class="quiz-panel d-flex justify-content-between align-items-center gap-2" data-question-navigation><button class="btn btn-outline-secondary" type="button" data-question-prev><i class="ti ti-arrow-left"></i> Previous</button><strong data-question-index>Question 1</strong><button class="btn btn-outline-primary" type="button" data-question-next>Next <i class="ti ti-arrow-right"></i></button></div>
<div class="quiz-panel d-flex justify-content-between align-items-center"><span data-answer-count>0 of {{ $attempt->assignment->quiz->questions->count() }} answered</span><button class="btn btn-primary" type="submit">Submit quiz</button></div></form></div>
@endsection
@section('scripts')<script src="{{ asset('js/quiz-attempt.js') }}"></script>@endsection
