<article class="learning-class-card">
    <div class="learning-class-mark" aria-hidden="true"><i class="ti ti-school"></i></div>
    <div class="learning-class-copy">
        <span class="learning-status {{ $schoolClass->isArchived() ? 'is-archived' : '' }}">{{ $schoolClass->isArchived() ? 'Archived' : 'Active class' }}</span>
        <h3><a href="{{ route('classes.show', $schoolClass) }}">{{ $schoolClass->name }}</a></h3>
        <p>{{ $schoolClass->description ?: 'Open this class to explore its channels and discussions.' }}</p>
        @if($detailed ?? false)
            <div class="learning-class-meta"><span>{{ $schoolClass->members_count }} members</span><span>{{ $schoolClass->channels->count() }} channels</span></div>
            @if($schoolClass->creator)
                <p class="learning-class-meta">Created by {{ $schoolClass->creator->name }}</p>
            @endif
            @can('manageMembers', $schoolClass)
                <p class="learning-class-meta">Join code: <strong>{{ $schoolClass->join_code }}</strong></p>
            @endcan
        @else
            <p class="learning-class-meta">Class updated {{ $schoolClass->updated_at->diffForHumans() }}</p>
        @endif
    </div>
    <div class="learning-class-links">
        <a class="btn btn-outline-primary" href="{{ route('classes.show', $schoolClass) }}">Open Class <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
        @canany(['manageLifecycle', 'manageMembers', 'manageChannels'], $schoolClass)
            <a class="learning-manage-link" href="{{ route('classes.show', $schoolClass) }}#class-actions">Manage class</a>
        @endcanany
    </div>
</article>
