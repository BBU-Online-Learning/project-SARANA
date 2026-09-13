<div class="learning-heading">
    <div><p class="learning-eyebrow">Learning dashboard</p><h1>Your next step starts here.</h1><p>Welcome, {{ $user->name }}. Open a class to read announcements and join the discussion.</p></div>
    <a class="btn btn-outline-primary" href="{{ route('classes.index') }}#join-class"><i class="ti ti-plus" aria-hidden="true"></i> Join Class</a>
</div>
<section aria-labelledby="learning-classes">
    <div class="learning-section-heading"><div><h2 id="learning-classes">Your recent classes</h2><p>{{ $classCounts['active'] }} active · {{ $classCounts['archived'] }} archived</p></div><a href="{{ route('classes.index') }}">Open my classes</a></div>
    <div class="learning-class-grid">
        @forelse($recentClasses as $schoolClass)
            @include('classes.partials.learning-card', ['detailed' => false])
        @empty
            @include('dashboard.learning-empty')
        @endforelse
    </div>
</section>
@include('dashboard.learning-conversations')
