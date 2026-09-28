@php
    $canCreateClass = auth()->user()->can('manage-classes');
@endphp
<div class="page-container learning-page">
    <section class="classes-page-hero" aria-labelledby="classes-page-title">
        <div class="classes-page-hero-copy">
            <p class="learning-eyebrow">{{ auth()->user()->role->name === 'teacher' ? 'Teaching workspace' : 'Learning workspace' }}</p>
            <h1 id="classes-page-title">My Classes</h1>
            <p>{{ $classIntroduction }}</p>
            <div class="classes-page-actions">
                @can('manage-classes')
                    <a class="btn classes-primary-action" href="#create-class"><i class="ti ti-plus" aria-hidden="true"></i> Create Class</a>
                @endcan
                <a class="btn classes-secondary-action" href="#join-class"><i class="ti ti-login" aria-hidden="true"></i> Join Class</a>
            </div>
        </div>
        <div class="classes-page-hero-art" aria-hidden="true"><i class="ti ti-books"></i></div>
    </section>

    <section class="classes-summary" aria-label="Class summary">
        <a href="{{ route('classes.index') }}"><span class="is-blue"><i class="ti ti-layout-grid"></i></span><span><strong>{{ $classSummary['total'] }}</strong><small>Total classes</small></span></a>
        <a href="{{ route('classes.index', ['status' => 'active']) }}"><span class="is-teal"><i class="ti ti-circle-check"></i></span><span><strong>{{ $classSummary['active'] }}</strong><small>Active classes</small></span></a>
        <a href="{{ route('classes.index', ['status' => 'archived']) }}"><span class="is-amber"><i class="ti ti-archive"></i></span><span><strong>{{ $classSummary['archived'] }}</strong><small>Archived classes</small></span></a>
    </section>

    <section class="learning-enrollment classes-enrollment" aria-label="{{ $canCreateClass ? 'Create or join a class' : 'Join a class' }}">
        <header class="classes-section-heading"><div><p>{{ $canCreateClass ? 'Class setup' : 'Class enrollment' }}</p><h2>{{ $canCreateClass ? 'Create or join a space' : 'Join a class' }}</h2><span>{{ $canCreateClass ? 'Use the options below to start teaching or enter an existing classroom.' : 'Enter the class code from your teacher to join your learning space.' }}</span></div></header>
        @include('classes.partials.enrollment-forms')
    </section>

    <section class="classes-directory" aria-labelledby="classes-directory-title">
        <header class="classes-section-heading"><div><p>Class directory</p><h2 id="classes-directory-title">Your class spaces</h2><span>Search, open, and manage the classes available to you.</span></div><strong>{{ $classes->count() }} shown</strong></header>
        @include('classes.partials.filters')
        <div class="learning-class-grid classes-page-grid">
        @forelse($classes as $schoolClass)
            @include('classes.partials.learning-card', ['detailed' => true])
        @empty
            @if($search !== '' || $status !== 'all')
                <p class="p-3">No classes match these filters. Try another search or <a href="{{ route('classes.index') }}">clear filters</a>.</p>
            @else
                @include('dashboard.learning-empty')
            @endif
        @endforelse
        </div>
    </section>
</div>
