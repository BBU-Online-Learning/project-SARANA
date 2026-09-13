<section class="card workspace-hero mb-4" aria-labelledby="dashboard-heading">
    <div class="card-body">
        <div class="workspace-eyebrow">{{ $dashboardTitle }}</div>
        <h1 class="h2 mt-2" id="dashboard-heading">{{ $dashboardHeading }}</h1>
        <p class="workspace-greeting">Welcome, {{ $user->name }}</p>
        <p class="text-muted workspace-intro">{{ $dashboardDescription }}</p>
        <div class="d-flex flex-wrap gap-2 mt-4">
            @can('access-admin')
                <a class="btn btn-primary" href="{{ route('users.index') }}">Manage accounts</a>
                <a class="btn btn-outline-primary" href="{{ route('classes.index') }}">Class administration</a>
            @else
                <a class="btn btn-primary" href="{{ route('classes.index') }}">Open my classes</a>
                @can('manage-classes')
                    <a class="btn btn-outline-primary" href="{{ route('classes.index') }}#create-class">Create Class</a>
                @endcan
                <a class="btn btn-outline-secondary" href="{{ route('classes.index') }}#join-class">Join Class</a>
            @endcan
        </div>
        <div class="workspace-focus"><i class="ti ti-school" aria-hidden="true"></i> {{ $dashboardFocus }}</div>
    </div>
</section>
