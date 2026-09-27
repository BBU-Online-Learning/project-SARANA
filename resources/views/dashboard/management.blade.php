@php
    $isSuperAdmin = $user->role->name === 'super_admin';
    $accountTotal = array_sum($accountCounts);
@endphp
<section class="teacher-dashboard-hero management-dashboard-hero {{ $isSuperAdmin ? 'is-super-admin' : 'is-admin' }}" aria-labelledby="management-dashboard-title">
    <div class="teacher-dashboard-hero-copy">
        <p class="learning-eyebrow">{{ $dashboardTitle }}</p>
        <h1 id="management-dashboard-title">{{ $dashboardHeading }}</h1>
        <p>Welcome, {{ $user->name }}. {{ $dashboardDescription }}</p>
        <div class="teacher-dashboard-actions">
            <a class="btn teacher-primary-action" href="{{ route('users.index') }}"><i class="ti ti-users" aria-hidden="true"></i> Manage Accounts</a>
            <a class="btn teacher-secondary-action" href="{{ route('classes.index') }}"><i class="ti ti-school" aria-hidden="true"></i> Class Administration</a>
        </div>
    </div>
    <div class="teacher-dashboard-hero-art" aria-hidden="true"><span class="teacher-hero-icon"><i class="ti {{ $isSuperAdmin ? 'ti-shield-lock' : 'ti-settings-cog' }}"></i></span><span class="teacher-hero-orbit teacher-hero-orbit-one"></span><span class="teacher-hero-orbit teacher-hero-orbit-two"></span></div>
</section>

<section class="teacher-metrics management-metrics" aria-label="Management metrics">
    <a href="{{ route('users.index') }}" class="teacher-metric teacher-metric-violet"><span class="teacher-metric-icon"><i class="ti ti-users"></i></span><span class="teacher-metric-copy"><small>Managed accounts</small><strong>{{ $accountTotal }}</strong><span>{{ $isSuperAdmin ? 'Administrators, teachers, students' : 'Teachers and students' }}</span></span><i class="ti ti-arrow-up-right teacher-metric-arrow"></i></a>
    <a href="{{ route('classes.index', ['status' => 'active']) }}" class="teacher-metric teacher-metric-blue"><span class="teacher-metric-icon"><i class="ti ti-school"></i></span><span class="teacher-metric-copy"><small>Active classes</small><strong data-count="active-classes">{{ $classCounts['active'] }}</strong><span>Institution class spaces</span></span><i class="ti ti-arrow-up-right teacher-metric-arrow"></i></a>
    <a href="{{ route('classes.index', ['status' => 'archived']) }}" class="teacher-metric teacher-metric-amber"><span class="teacher-metric-icon"><i class="ti ti-archive"></i></span><span class="teacher-metric-copy"><small>Archived classes</small><strong data-count="archived-classes">{{ $classCounts['archived'] }}</strong><span>Retained class history</span></span><i class="ti ti-arrow-up-right teacher-metric-arrow"></i></a>
    <a href="{{ route('chat.index') }}" class="teacher-metric teacher-metric-teal"><span class="teacher-metric-icon"><i class="ti ti-messages"></i></span><span class="teacher-metric-copy"><small>Conversations</small><strong>{{ array_sum($conversationCounts) }}</strong><span>{{ $conversationCounts['direct'] }} direct · {{ $conversationCounts['group'] }} groups</span></span><i class="ti ti-arrow-up-right teacher-metric-arrow"></i></a>
</section>

<div class="teacher-dashboard-layout management-dashboard-layout">
    <section class="teacher-dashboard-panel" aria-labelledby="managed-accounts-title">
        <header class="teacher-panel-heading"><div><p class="teacher-section-kicker">Account directory</p><h2 id="managed-accounts-title">{{ $isSuperAdmin ? 'Institution Accounts' : 'Teachers and Students' }}</h2><span>Current active and suspended accounts within your authority.</span></div><a href="{{ route('users.index') }}">Manage accounts <i class="ti ti-arrow-right"></i></a></header>
        <div class="management-account-grid">
            @foreach($accountCounts as $role => $count)
                <a href="{{ route('users.index', ['role' => $role]) }}" class="management-account-card role-{{ str_replace('_', '-', $role) }}"><span><i class="ti {{ $role === 'admin' ? 'ti-shield' : ($role === 'teacher' ? 'ti-chalkboard' : 'ti-school') }}"></i></span><div><strong data-count="{{ $role }}-accounts">{{ $count }}</strong><small>{{ ucwords(str_replace('_', ' ', $role)) }} accounts</small></div><i class="ti ti-chevron-right"></i></a>
            @endforeach
        </div>
        <div class="management-focus"><i class="ti ti-lock-access" aria-hidden="true"></i><span><strong>Your administration scope</strong><small>{{ $dashboardFocus }}</small></span></div>
    </section>

    <aside class="teacher-dashboard-panel management-recent-panel" aria-labelledby="recent-classes-title">
        <header class="teacher-panel-heading"><div><p class="teacher-section-kicker">Recent activity</p><h2 id="recent-classes-title">Updated Classes</h2><span>Recently changed institution class spaces.</span></div></header>
        <div class="management-recent-classes">
            @forelse($recentClasses as $schoolClass)
                <a href="{{ route('classes.show', $schoolClass) }}"><span class="management-class-icon"><i class="ti ti-school"></i></span><span><strong>{{ $schoolClass->name }}</strong><small>{{ $schoolClass->members_count }} members · {{ $schoolClass->channels_count }} channels</small></span><span class="learning-status {{ $schoolClass->isArchived() ? 'is-archived' : '' }}">{{ $schoolClass->isArchived() ? 'Archived' : 'Active' }}</span></a>
            @empty
                <div class="teacher-deadline-empty"><i class="ti ti-school-off"></i><span>No institution classes yet.</span></div>
            @endforelse
        </div>
        <a class="management-panel-action" href="{{ route('classes.index') }}">Open class administration <i class="ti ti-arrow-right"></i></a>
    </aside>
</div>

<section class="teacher-quick-actions management-quick-actions" aria-labelledby="management-actions-title">
    <header><div><p class="teacher-section-kicker">Administration</p><h2 id="management-actions-title">Quick Actions</h2></div></header>
    <div>
        <a href="{{ route('users.index') }}"><span class="is-violet"><i class="ti ti-user-cog"></i></span><strong>Manage Accounts</strong><small>Create, update, or review accounts</small></a>
        <a href="{{ route('classes.index') }}"><span class="is-blue"><i class="ti ti-school"></i></span><strong>Class Administration</strong><small>Owners, members, and classes</small></a>
        <a href="{{ route('roles.index') }}"><span class="is-amber"><i class="ti ti-shield-check"></i></span><strong>Fixed Roles</strong><small>Review role permissions</small></a>
        <a href="{{ route('chat.index') }}"><span class="is-teal"><i class="ti ti-message-circle"></i></span><strong>Open Chat</strong><small>Continue conversations</small></a>
    </div>
</section>
