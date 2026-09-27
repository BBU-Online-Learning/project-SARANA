@php
    $workspaceIdentity = match (auth()->user()->role->name) {
        'super_admin' => ['Super Admin', 'System management', 'ti-shield-lock'],
        'admin' => ['Admin', 'School management', 'ti-shield-check'],
        'teacher' => ['Teacher', 'Teaching workspace', 'ti-chalkboard'],
        default => ['Student', 'Learning workspace', 'ti-school'],
    };
@endphp
@if($sidebarIdentity ?? false)
    <span class="workspace-sidebar-identity">
        <span class="workspace-role-indicator" aria-hidden="true"><i class="ti {{ $workspaceIdentity[2] }}"></i></span>
        <span class="workspace-role-copy">
            <strong>{{ $workspaceIdentity[0] }}</strong>
            <small>{{ $workspaceIdentity[1] }}</small>
        </span>
    </span>
@else
    <span class="workspace-role-badge">{{ $workspaceIdentity[0] }} · {{ $workspaceIdentity[1] }}</span>
@endif
