@extends('layouts.app')
@section('title', 'Institution roles')

@section('content')
    <div class="page-container workspace-page roles-page">
        <section class="workspace-page-heading roles-page-heading mb-4" aria-labelledby="roles-page-title">
            <div class="workspace-heading-icon" aria-hidden="true"><i class="ti ti-shield-check"></i></div>
            <div class="workspace-heading-copy">
                <p class="workspace-eyebrow mb-1">Access and responsibility</p>
                <h1 class="h3 mb-1" id="roles-page-title">Institution Roles</h1>
                <p class="mb-0">Understand the fixed access levels used throughout the learning platform.</p>
            </div>
        </section>
        <div class="workspace-info-panel" role="note">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            <p>Roles are fixed. Super Admin manages Admin, Teacher and Student accounts. Admin manages Teacher and Student accounts only. Super Admin accounts cannot be edited or deleted through account management. Administrative privileges do not grant access to private conversations.</p>
        </div>
        <div class="card workspace-data-card roles-table-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Role</th><th>Definition</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($roles as $role)
                            @php
                                $roleState = $role->trashed() ? 'preserved' : ($role->status ? 'active' : 'inactive');
                            @endphp
                            <tr>
                                <td data-label="Role"><span class="workspace-badge role-badge role-{{ str_replace('_', '-', $role->name) }}">{{ ucwords(str_replace('_', ' ', $role->name)) }}</span></td>
                                <td data-label="Definition">{{ in_array($role->name, \App\Models\Role::NAMES, true) ? 'Fixed institution role' : 'Legacy role (preserved; not assignable)' }}</td>
                                <td data-label="Status"><span class="workspace-badge status-badge status-{{ $roleState }}">{{ $role->trashed() ? 'Deleted (preserved)' : ($role->status ? 'Enabled' : 'Disabled') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
