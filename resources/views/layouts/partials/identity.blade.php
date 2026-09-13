@php
    $workspaceLabel = match (auth()->user()->role->name) {
        'super_admin' => 'Super Admin · System management',
        'admin' => 'Admin · School management',
        'teacher' => 'Teacher · Teaching',
        default => 'Student · Learning',
    };
@endphp
<span class="workspace-role-badge">{{ $workspaceLabel }}</span>
