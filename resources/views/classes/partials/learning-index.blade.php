<div class="page-container learning-page">
    <div class="learning-heading"><div><p class="learning-eyebrow">{{ auth()->user()->role->name === 'teacher' ? 'Teaching workspace' : 'Learning workspace' }}</p><h1>My Classes</h1><p>{{ $classIntroduction }}</p></div>
        @can('manage-classes')
            <a class="btn btn-primary" href="#create-class">Create Class</a>
        @else
            <a class="btn btn-primary" href="#join-class">Join Class</a>
        @endcan
    </div>
    <div class="learning-section-heading"><h2>Your class spaces</h2><span class="learning-muted">{{ $classes->count() }} classes</span></div>
    <div class="{{ auth()->user()->role->name === 'teacher' ? 'learning-class-list learning-panel' : 'learning-class-grid' }}">
        @forelse($classes as $schoolClass)
            @include('classes.partials.learning-card', ['detailed' => true])
        @empty
            @include('dashboard.learning-empty')
        @endforelse
    </div>
    <section class="learning-enrollment" aria-label="Create or join a class">
        @include('classes.partials.enrollment-forms')
    </section>
</div>
