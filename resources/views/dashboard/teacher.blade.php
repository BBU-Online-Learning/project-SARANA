<div class="learning-heading">
    <div><p class="learning-eyebrow">Teaching dashboard</p><h1>Ready for your next class?</h1><p>Welcome, {{ $user->name }}. Keep your classes organized and your students connected.</p></div>
    @can('manage-classes')
        <a class="btn btn-primary" href="{{ route('classes.index') }}#create-class"><i class="ti ti-plus" aria-hidden="true"></i> Create Class</a>
    @endcan
</div>
<div class="learning-stats" aria-label="Your workspace counts">
    <a href="{{ route('classes.index') }}" class="learning-stat"><span>Active classes</span><strong data-count="active-classes">{{ $classCounts['active'] }}</strong></a>
    <a href="{{ route('classes.index') }}" class="learning-stat"><span>Archived classes</span><strong data-count="archived-classes">{{ $classCounts['archived'] }}</strong></a>
    <a href="{{ route('chat.index') }}" class="learning-stat"><span>Conversations</span><strong>{{ array_sum($conversationCounts) }}</strong></a>
</div>
<section class="learning-panel" aria-labelledby="teaching-classes">
    <div class="learning-panel-heading"><div><h2 id="teaching-classes">Your class spaces</h2><p>Recently updated classes you belong to.</p></div><a href="{{ route('classes.index') }}">Open my classes</a></div>
    <div class="learning-class-list">
        @forelse($recentClasses as $schoolClass)
            @include('classes.partials.learning-card', ['detailed' => false])
        @empty
            @include('dashboard.learning-empty')
        @endforelse
    </div>
</section>
<div class="learning-footer"><p>Manage members and channels inside each class, according to your permissions.</p><a href="{{ route('classes.index') }}#join-class">Join Class</a></div>
@include('dashboard.learning-conversations')
