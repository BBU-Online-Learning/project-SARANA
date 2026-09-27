<section class="teacher-dashboard-hero" aria-labelledby="teacher-dashboard-title">
    <div class="teacher-dashboard-hero-copy">
        <p class="learning-eyebrow">Teaching dashboard</p>
        <h1 id="teacher-dashboard-title">Ready for your next class?</h1>
        <p>Welcome, {{ $user->name }}. Manage classes, assessments, grading, and conversations from one place.</p>
        <div class="teacher-dashboard-actions">
            @can('manage-classes')
                <a class="btn teacher-primary-action" href="{{ route('classes.index') }}#create-class"><i class="ti ti-plus" aria-hidden="true"></i> Create Class</a>
            @endcan
            <a class="btn teacher-secondary-action" href="{{ route('assessments.index') }}"><i class="ti ti-clipboard-check" aria-hidden="true"></i> Create Quiz</a>
        </div>
    </div>
    <div class="teacher-dashboard-hero-art" aria-hidden="true">
        <span class="teacher-hero-icon"><i class="ti ti-school"></i></span>
        <span class="teacher-hero-orbit teacher-hero-orbit-one"></span>
        <span class="teacher-hero-orbit teacher-hero-orbit-two"></span>
    </div>
</section>

<section class="teacher-metrics" aria-label="Teaching overview">
    <a href="{{ route('classes.index', ['status' => 'active']) }}" class="teacher-metric teacher-metric-blue">
        <span class="teacher-metric-icon"><i class="ti ti-school" aria-hidden="true"></i></span>
        <span class="teacher-metric-copy"><small>Active classes</small><strong data-count="active-classes">{{ $classCounts['active'] }}</strong><span>{{ $classCounts['archived'] }} archived</span></span>
        <i class="ti ti-arrow-up-right teacher-metric-arrow" aria-hidden="true"></i>
    </a>
    <a href="{{ route('assessments.index') }}" class="teacher-metric teacher-metric-violet">
        <span class="teacher-metric-icon"><i class="ti ti-clipboard-check" aria-hidden="true"></i></span>
        <span class="teacher-metric-copy"><small>Quizzes</small><strong>{{ $assessmentSummary['quizzes'] }}</strong><span>{{ $assessmentSummary['active_assignments'] }} currently assigned</span></span>
        <i class="ti ti-arrow-up-right teacher-metric-arrow" aria-hidden="true"></i>
    </a>
    <a href="{{ route('assessments.index') }}" class="teacher-metric teacher-metric-amber">
        <span class="teacher-metric-icon"><i class="ti ti-list-check" aria-hidden="true"></i></span>
        <span class="teacher-metric-copy"><small>Needs grading</small><strong>{{ $assessmentSummary['pending_reviews'] }}</strong><span>{{ $assessmentSummary['pending_reviews'] === 1 ? 'submission waiting' : 'submissions waiting' }}</span></span>
        <i class="ti ti-arrow-up-right teacher-metric-arrow" aria-hidden="true"></i>
    </a>
    <a href="{{ route('chat.index') }}" class="teacher-metric teacher-metric-teal">
        <span class="teacher-metric-icon"><i class="ti ti-messages" aria-hidden="true"></i></span>
        <span class="teacher-metric-copy"><small>Conversations</small><strong>{{ array_sum($conversationCounts) }}</strong><span>{{ $conversationCounts['direct'] }} direct · {{ $conversationCounts['group'] }} groups</span></span>
        <i class="ti ti-arrow-up-right teacher-metric-arrow" aria-hidden="true"></i>
    </a>
</section>

<div class="teacher-dashboard-layout">
    <section class="teacher-dashboard-panel teacher-classes-panel" aria-labelledby="teaching-classes">
        <header class="teacher-panel-heading">
            <div><p class="teacher-section-kicker">Classroom spaces</p><h2 id="teaching-classes">My Classes</h2><span>Manage your classes and student communities.</span></div>
            <a href="{{ route('classes.index') }}">Open my classes <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
        </header>
        <div class="teacher-class-grid">
            @forelse($recentClasses as $schoolClass)
                @include('classes.partials.learning-card', ['detailed' => false])
            @empty
                @include('dashboard.learning-empty')
            @endforelse
        </div>
    </section>

    <aside class="teacher-dashboard-panel teacher-overview-panel" aria-labelledby="teaching-overview-title">
        <header class="teacher-panel-heading">
            <div><p class="teacher-section-kicker">At a glance</p><h2 id="teaching-overview-title">Teaching Overview</h2><span>Assessment work that needs your attention.</span></div>
        </header>
        <div class="teacher-overview-list">
            <a href="{{ route('assessments.index') }}"><span class="teacher-overview-icon is-violet"><i class="ti ti-file-pencil"></i></span><span><strong>{{ $assessmentSummary['quizzes'] }}</strong><small>Total quizzes</small></span><i class="ti ti-chevron-right"></i></a>
            <a href="{{ route('assessments.index') }}"><span class="teacher-overview-icon is-blue"><i class="ti ti-calendar-due"></i></span><span><strong>{{ $assessmentSummary['active_assignments'] }}</strong><small>Active assignments</small></span><i class="ti ti-chevron-right"></i></a>
            <a href="{{ route('assessments.index') }}"><span class="teacher-overview-icon is-amber"><i class="ti ti-checklist"></i></span><span><strong>{{ $assessmentSummary['pending_reviews'] }}</strong><small>Awaiting review</small></span><i class="ti ti-chevron-right"></i></a>
        </div>

        <div class="teacher-upcoming">
            <div class="teacher-upcoming-heading"><h3>Upcoming deadlines</h3><span>{{ $upcomingAssessments->count() }}</span></div>
            @forelse($upcomingAssessments as $assignment)
                <a class="teacher-deadline" href="{{ route('classes.assessments.index', $assignment->schoolClass) }}">
                    <span class="teacher-deadline-date"><strong>{{ $assignment->due_at->format('d') }}</strong><small>{{ $assignment->due_at->format('M') }}</small></span>
                    <span><strong>{{ $assignment->quiz->title }}</strong><small>{{ $assignment->schoolClass->name }} · {{ $assignment->due_at->format('g:i A') }}</small></span>
                </a>
            @empty
                <div class="teacher-deadline-empty"><i class="ti ti-calendar-check" aria-hidden="true"></i><span>No upcoming assessment deadlines.</span></div>
            @endforelse
        </div>
    </aside>
</div>

<section class="teacher-quick-actions" aria-labelledby="teacher-quick-actions-title">
    <header><div><p class="teacher-section-kicker">Shortcuts</p><h2 id="teacher-quick-actions-title">Quick Actions</h2></div></header>
    <div>
        @can('manage-classes')<a href="{{ route('classes.index') }}#create-class"><span class="is-blue"><i class="ti ti-school"></i></span><strong>Create Class</strong><small>Start a new class space</small></a>@endcan
        <a href="{{ route('assessments.index') }}"><span class="is-violet"><i class="ti ti-file-plus"></i></span><strong>Create Quiz</strong><small>Build and assign an assessment</small></a>
        <a href="{{ route('classes.index') }}"><span class="is-teal"><i class="ti ti-layout-grid"></i></span><strong>Manage Classes</strong><small>Members, channels, and settings</small></a>
        <a href="{{ route('chat.index') }}"><span class="is-amber"><i class="ti ti-message-circle"></i></span><strong>Open Chat</strong><small>Continue conversations</small></a>
    </div>
</section>

<div class="learning-footer"><p>Class and assessment actions follow your teaching permissions.</p><span><a href="{{ route('classes.index', ['status' => 'archived']) }}">Archived classes</a> · <a href="{{ route('classes.index') }}#join-class">Join an existing class</a></span></div>
