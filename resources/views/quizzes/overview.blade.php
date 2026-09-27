@extends('layouts.app')
@section('title', $teacher ? 'Assessments' : 'My Quizzes')
@section('styles')<link rel="stylesheet" href="{{ asset('css/quiz.css') }}">@endsection
@section('content')
<div class="page-container quiz-page quiz-overview">
    <header class="quiz-overview-intro">
        <div class="quiz-overview-intro-copy">
            <span class="quiz-overview-kicker"><i class="ti ti-clipboard-check" aria-hidden="true"></i> {{ $teacher ? 'Teaching workspace' : 'Learning workspace' }}</span>
            <h1>{{ $teacher ? 'Assessments' : 'My quizzes' }}</h1>
            <p>{{ $teacher ? 'Build, assign, and review quizzes for your classes.' : 'Find your next quiz, continue an attempt, or check your results.' }}</p>
        </div>
        <a class="btn btn-outline-primary quiz-overview-classes-link" href="{{ route('classes.index') }}"><i class="ti ti-school" aria-hidden="true"></i> View classes</a>
    </header>

    @if($teacher)
        @if($classes->isNotEmpty())
        <div class="quiz-overview-summary" aria-label="Assessment overview">
            <a class="quiz-overview-summary-item" href="#assessment-classes-heading"><span class="quiz-overview-summary-icon is-blue"><i class="ti ti-school" aria-hidden="true"></i></span><span><strong>{{ $classes->count() }}</strong><span>{{ Str::plural('Class', $classes->count()) }}</span><small>Choose where to work</small></span><i class="ti ti-arrow-up-right quiz-overview-summary-arrow" aria-hidden="true"></i></a>
            <a class="quiz-overview-summary-item" href="#recent-assessments-heading"><span class="quiz-overview-summary-icon is-violet"><i class="ti ti-files" aria-hidden="true"></i></span><span><strong>{{ $quizzes->count() }}</strong><span>{{ Str::plural('Quiz', $quizzes->count()) }} created</span><small>Across your classes</small></span><i class="ti ti-arrow-up-right quiz-overview-summary-arrow" aria-hidden="true"></i></a>
            <a class="quiz-overview-summary-item" href="#recent-assessments-heading"><span class="quiz-overview-summary-icon is-green"><i class="ti ti-circle-check" aria-hidden="true"></i></span><span><strong>{{ $quizzes->filter(fn ($quiz) => $quiz->status === 'published' && !$quiz->schoolClass->isArchived())->count() }}</strong><span>Ready to assign</span><small>Published quizzes</small></span><i class="ti ti-arrow-up-right quiz-overview-summary-arrow" aria-hidden="true"></i></a>
        </div>
        @endif

        <section class="quiz-overview-section" aria-labelledby="assessment-classes-heading">
            <div class="quiz-overview-heading"><div><span class="quiz-overview-label">Start here</span><h2 id="assessment-classes-heading">Choose a class</h2><p>Create a quiz in a class or open its existing assessments.</p></div><span class="quiz-overview-count">{{ $classes->count() }} {{ Str::plural('class', $classes->count()) }}</span></div>
            <div class="quiz-overview-class-grid">
                @forelse($classes as $class)
                    <article class="quiz-overview-class-card">
                        <div class="quiz-overview-class-top"><span class="quiz-overview-class-icon" aria-hidden="true"><i class="ti ti-school"></i></span><span class="quiz-overview-class-state {{ $class->isArchived() ? 'is-archived' : '' }}">{{ $class->isArchived() ? 'Archived' : 'Active class' }}</span></div>
                        <h3>{{ $class->name }}</h3>
                        <p>{{ $class->isArchived() ? 'View past quizzes and results for this class.' : 'Build new quizzes and manage assignments in this class.' }}</p>
                        <div class="quiz-overview-class-actions">
                            @unless($class->isArchived())
                                <a class="btn btn-primary" href="{{ route('classes.quizzes.create', $class) }}"><i class="ti ti-plus" aria-hidden="true"></i> Create quiz</a>
                            @endunless
                            <a class="btn btn-outline-primary" href="{{ route('classes.assessments.index', $class) }}">{{ $class->isArchived() ? 'View assessments' : 'Manage quizzes' }} <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </article>
                @empty
                    <div class="quiz-overview-empty"><span class="quiz-overview-empty-icon" aria-hidden="true"><i class="ti ti-school"></i></span><h3>No teaching classes yet</h3><p>Create or join a class as a teacher before building an assessment.</p><a class="btn btn-primary" href="{{ route('classes.index') }}">Go to classes</a></div>
                @endforelse
            </div>
        </section>

        @if($classes->isNotEmpty())
        <section class="quiz-overview-section" aria-labelledby="recent-assessments-heading">
            <div class="quiz-overview-heading"><div><span class="quiz-overview-label">Your library</span><h2 id="recent-assessments-heading">Recent assessments</h2><p>Pick up a draft, assign a published quiz, or review results.</p></div><span class="quiz-overview-count">{{ $quizzes->count() }} {{ Str::plural('quiz', $quizzes->count()) }}</span></div>
            @if($quizzes->isNotEmpty())
                <div class="quiz-overview-library">
                    @foreach($quizzes as $quiz)
                        @php($latestAssignment = $quiz->assignments->first())
                        <article class="quiz-overview-quiz-row">
                            <span class="quiz-overview-quiz-icon" aria-hidden="true"><i class="ti ti-file-text"></i></span>
                            <div class="quiz-overview-quiz-main"><div class="quiz-overview-quiz-title"><h3>{{ $quiz->title }}</h3><span class="quiz-status {{ $quiz->status === 'published' ? 'available' : '' }}">{{ $quiz->status }}</span></div><p>{{ $quiz->schoolClass->name }} <span aria-hidden="true">·</span> {{ $quiz->questions_count }} {{ Str::plural('question', $quiz->questions_count) }} <span aria-hidden="true">·</span> {{ $quiz->assignments_count }} {{ Str::plural('assignment', $quiz->assignments_count) }}</p></div>
                            <div class="quiz-overview-quiz-actions">
                                @if($latestAssignment)
                                    <a class="btn btn-primary" href="{{ route('classes.quiz-assignments.results', [$quiz->schoolClass, $latestAssignment]) }}">Review results</a>
                                @elseif($quiz->status === 'published' && !$quiz->schoolClass->isArchived())
                                    <a class="btn btn-primary" href="{{ route('classes.quizzes.assign.create', [$quiz->schoolClass, $quiz]) }}">Assign quiz</a>
                                @elseif(!$quiz->schoolClass->isArchived())
                                    <a class="btn btn-primary" href="{{ route('classes.quizzes.edit', [$quiz->schoolClass, $quiz]) }}">Continue building</a>
                                @endif
                                <a class="btn btn-outline-primary" href="{{ route('classes.assessments.index', $quiz->schoolClass) }}">Open class</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="quiz-overview-empty"><span class="quiz-overview-empty-icon" aria-hidden="true"><i class="ti ti-file-plus"></i></span><h3>Your quiz library is empty</h3><p>Choose a class above to create your first quiz.</p><a class="btn btn-outline-primary" href="#assessment-classes-heading">Choose a class</a></div>
            @endif
        </section>
        @endif
    @else
        <section class="quiz-overview-section quiz-overview-student-section" aria-labelledby="student-assessments-heading">
            <div class="quiz-overview-heading"><div><span class="quiz-overview-label">Your learning</span><h2 id="student-assessments-heading">Assigned quizzes</h2><p>Start what is available and return to saved attempts here.</p></div><span class="quiz-overview-count">{{ $assignments->count() }} {{ Str::plural('quiz', $assignments->count()) }}</span></div>
            <div class="quiz-overview-student-grid">
                @forelse($assignments as $assignment)
                    @php($attempt = $assignment->attempts->sortByDesc('attempt_number')->first())
                    <article class="quiz-overview-student-card">
                        <div class="quiz-overview-student-top"><span class="quiz-overview-class-icon" aria-hidden="true"><i class="ti ti-clipboard-text"></i></span><span class="quiz-status {{ $attempt?->status ?? $assignment->availabilityStatus() }}">{{ str_replace('_', ' ', $attempt?->status ?? $assignment->availabilityStatus()) }}</span></div>
                        <h3>{{ $assignment->quiz->title }}</h3><p class="quiz-overview-student-class">{{ $assignment->schoolClass->name }}</p>
                        <p class="quiz-overview-student-description">{{ Str::limit($assignment->instructions ?: $assignment->quiz->description ?: 'Open this quiz to see the details.', 140) }}</p>
                        <div class="quiz-overview-student-meta"><span><i class="ti ti-list-numbers" aria-hidden="true"></i> {{ $assignment->quiz->questions_count }} {{ Str::plural('question', $assignment->quiz->questions_count) }}</span><span><i class="ti ti-calendar-event" aria-hidden="true"></i> Due {{ $assignment->due_at->format('M j, Y g:i A') }}</span></div>
                        <div class="quiz-overview-student-actions">
                            @if($attempt?->status === 'in_progress')
                                <a class="btn btn-primary" href="{{ route('classes.quiz-attempts.show', [$assignment->schoolClass, $attempt]) }}">Continue quiz</a>
                            @elseif($assignment->availabilityStatus() === 'available' && $assignment->attempts->count() < $assignment->attempt_limit)
                                <form method="POST" action="{{ route('classes.quiz-assignments.start', [$assignment->schoolClass, $assignment]) }}">@csrf<button class="btn btn-primary" type="submit">{{ $attempt ? 'Start another attempt' : 'Start quiz' }}</button></form>
                            @elseif($attempt)
                                <a class="btn btn-primary" href="{{ route('classes.quiz-attempts.result', [$assignment->schoolClass, $attempt]) }}">View submission</a>
                            @endif
                            @if($attempt && $attempt->status !== 'in_progress' && $assignment->availabilityStatus() === 'available' && $assignment->attempts->count() < $assignment->attempt_limit)
                                <a class="btn btn-outline-primary" href="{{ route('classes.quiz-attempts.result', [$assignment->schoolClass, $attempt]) }}">View submission</a>
                            @endif
                            <a class="btn btn-outline-primary" href="{{ route('classes.assessments.index', $assignment->schoolClass) }}">Class assessments</a>
                        </div>
                    </article>
                @empty
                    <div class="quiz-overview-empty"><span class="quiz-overview-empty-icon" aria-hidden="true"><i class="ti ti-calendar-check"></i></span><h3>No quizzes assigned yet</h3><p>Quizzes from your classes will appear here when a teacher assigns them.</p><a class="btn btn-outline-primary" href="{{ route('classes.index') }}">View classes</a></div>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
