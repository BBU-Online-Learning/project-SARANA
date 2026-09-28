<section class="teacher-dashboard-hero student-dashboard-hero" aria-labelledby="student-dashboard-title">
    <div class="teacher-dashboard-hero-copy">
        <p class="learning-eyebrow">Learning dashboard</p>
        <h1 id="student-dashboard-title">Your next step starts here.</h1>
        <p>Welcome, {{ $user->name }}. Keep up with your classes, quizzes, results, and conversations.</p>
        <div class="teacher-dashboard-actions">
            <a class="btn teacher-primary-action" href="{{ route('classes.index') }}"><i class="ti ti-school" aria-hidden="true"></i> Open My Classes</a>
            <a class="btn teacher-secondary-action" href="{{ route('attendance.mine') }}"><i class="ti ti-calendar-check" aria-hidden="true"></i> My Attendance</a>
            <a class="btn teacher-secondary-action" href="{{ route('assessments.index') }}"><i class="ti ti-clipboard-check" aria-hidden="true"></i> My Quizzes</a>
        </div>
    </div>
    <div class="teacher-dashboard-hero-art" aria-hidden="true"><span class="teacher-hero-icon"><i class="ti ti-book-2"></i></span><span class="teacher-hero-orbit teacher-hero-orbit-one"></span><span class="teacher-hero-orbit teacher-hero-orbit-two"></span></div>
</section>

<section class="teacher-metrics student-metrics" aria-label="Learning overview">
    <a href="{{ route('classes.index', ['status' => 'active']) }}" class="teacher-metric teacher-metric-blue"><span class="teacher-metric-icon"><i class="ti ti-school"></i></span><span class="teacher-metric-copy"><small>Active classes</small><strong data-count="active-classes">{{ $classCounts['active'] }}</strong><span>{{ $classCounts['archived'] }} archived</span></span><i class="ti ti-arrow-up-right teacher-metric-arrow"></i></a>
    <a href="{{ route('assessments.index') }}" class="teacher-metric teacher-metric-violet"><span class="teacher-metric-icon"><i class="ti ti-clipboard-text"></i></span><span class="teacher-metric-copy"><small>Available quizzes</small><strong>{{ $studentAssessmentSummary['available'] }}</strong><span>{{ $studentAssessmentSummary['assigned'] }} total assigned</span></span><i class="ti ti-arrow-up-right teacher-metric-arrow"></i></a>
    <a href="{{ route('assessments.index') }}" class="teacher-metric teacher-metric-amber"><span class="teacher-metric-icon"><i class="ti ti-award"></i></span><span class="teacher-metric-copy"><small>Graded results</small><strong>{{ $studentAssessmentSummary['graded'] }}</strong><span>{{ $studentAssessmentSummary['in_progress'] }} in progress</span></span><i class="ti ti-arrow-up-right teacher-metric-arrow"></i></a>
    <a href="{{ route('chat.index') }}" class="teacher-metric teacher-metric-teal"><span class="teacher-metric-icon"><i class="ti ti-messages"></i></span><span class="teacher-metric-copy"><small>Conversations</small><strong>{{ array_sum($conversationCounts) }}</strong><span>{{ $conversationCounts['direct'] }} direct · {{ $conversationCounts['group'] }} groups</span></span><i class="ti ti-arrow-up-right teacher-metric-arrow"></i></a>
</section>

<div class="teacher-dashboard-layout student-dashboard-layout">
    <section class="teacher-dashboard-panel" aria-labelledby="learning-classes">
        <header class="teacher-panel-heading"><div><p class="teacher-section-kicker">Learning spaces</p><h2 id="learning-classes">My Recent Classes</h2><span>Continue learning in your recently updated classes.</span></div><a href="{{ route('classes.index') }}">Open my classes <i class="ti ti-arrow-right"></i></a></header>
        <div class="learning-class-grid student-dashboard-class-grid">
            @forelse($recentClasses as $schoolClass)
                @include('classes.partials.learning-card', ['detailed' => false])
            @empty
                @include('dashboard.learning-empty')
            @endforelse
        </div>
    </section>
    <aside class="teacher-dashboard-panel student-study-panel" aria-labelledby="student-overview-title">
        <header class="teacher-panel-heading"><div><p class="teacher-section-kicker">Study plan</p><h2 id="student-overview-title">Learning Overview</h2><span>Your quizzes and upcoming due dates.</span></div></header>
        <div class="teacher-overview-list">
            <a href="{{ route('assessments.index') }}"><span class="teacher-overview-icon is-violet"><i class="ti ti-clipboard-text"></i></span><span><strong>{{ $studentAssessmentSummary['assigned'] }}</strong><small>Assigned quizzes</small></span><i class="ti ti-chevron-right"></i></a>
            <a href="{{ route('assessments.index') }}"><span class="teacher-overview-icon is-blue"><i class="ti ti-player-play"></i></span><span><strong>{{ $studentAssessmentSummary['in_progress'] }}</strong><small>In progress</small></span><i class="ti ti-chevron-right"></i></a>
            <a href="{{ route('assessments.index') }}"><span class="teacher-overview-icon is-teal"><i class="ti ti-rosette-discount-check"></i></span><span><strong>{{ $studentAssessmentSummary['graded'] }}</strong><small>Graded attempts</small></span><i class="ti ti-chevron-right"></i></a>
        </div>
        <div class="teacher-upcoming">
            <div class="teacher-upcoming-heading"><h3>Upcoming quizzes</h3><span>{{ $studentUpcomingAssessments->count() }}</span></div>
            @forelse($studentUpcomingAssessments as $assignment)
                <a class="teacher-deadline" href="{{ route('classes.assessments.index', $assignment->schoolClass) }}"><span class="teacher-deadline-date"><strong>{{ $assignment->due_at->format('d') }}</strong><small>{{ $assignment->due_at->format('M') }}</small></span><span><strong>{{ $assignment->quiz->title }}</strong><small>{{ $assignment->schoolClass->name }} · {{ $assignment->due_at->format('g:i A') }}</small></span></a>
            @empty
                <div class="teacher-deadline-empty"><i class="ti ti-calendar-check"></i><span>No upcoming quiz deadlines.</span></div>
            @endforelse
        </div>
    </aside>
</div>

<section class="teacher-quick-actions student-quick-actions" aria-labelledby="student-actions-title">
    <header><div><p class="teacher-section-kicker">Shortcuts</p><h2 id="student-actions-title">Quick Actions</h2></div></header>
    <div>
        <a href="{{ route('classes.index') }}"><span class="is-blue"><i class="ti ti-school"></i></span><strong>My Classes</strong><small>Open your learning spaces</small></a>
        <a href="{{ route('assessments.index') }}"><span class="is-violet"><i class="ti ti-clipboard-check"></i></span><strong>My Quizzes</strong><small>Continue or review assessments</small></a>
        <a href="{{ route('chat.index') }}"><span class="is-teal"><i class="ti ti-message-circle"></i></span><strong>Open Chat</strong><small>Ask teachers and classmates</small></a>
        <a href="{{ route('classes.index') }}#join-class"><span class="is-amber"><i class="ti ti-login"></i></span><strong>Join Class</strong><small>Enter a teacher's class code</small></a>
    </div>
</section>
