@extends('layouts.app')
@section('title', 'Account administration')

@section('content')
    @error('user') <div class="alert alert-danger" role="alert">{{ $message }}</div> @enderror
    <div class="page-container workspace-page account-admin-page">
        <section class="workspace-page-heading account-page-heading mb-4" aria-labelledby="account-page-title">
            <div class="workspace-heading-icon" aria-hidden="true"><i class="ti ti-users"></i></div>
            <div class="workspace-heading-copy">
                <p class="workspace-eyebrow mb-1">School management</p>
                <h1 class="h3 mb-1" id="account-page-title">Account administration</h1>
                <p class="mb-0">Manage the teacher and student accounts available to your role.</p>
            </div>
            @can('create', \App\Models\User::class)
                <a href="{{ route('users.create') }}" class="btn btn-primary workspace-heading-action"><i class="ti ti-user-plus" aria-hidden="true"></i> Add User</a>
            @endcan
        </section>
        <p class="workspace-page-note"><i class="ti ti-shield-lock" aria-hidden="true"></i><span>Only accounts you may manage are listed. Super Admin and legacy accounts are protected.</span></p>
        <div class="card workspace-data-card account-table-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>2FA</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse ($users as $row)
                            <tr>
                                <td data-label="Name">{{ $row->name }}</td>
                                <td data-label="Role"><span class="workspace-badge role-badge role-{{ str_replace('_', '-', $row->role->name) }}">{{ ucwords(str_replace('_', ' ', $row->role->name)) }}</span></td>
                                <td data-label="Email">{{ $row->email }}</td>
                                <td data-label="Status"><span class="workspace-badge status-badge status-{{ $row->status ?: 'legacy' }}">{{ $row->status ? ucfirst($row->status) : 'Legacy status' }}</span></td>
                                <td data-label="2FA">
                                    <span class="workspace-badge {{ $row->google2fa_enabled ? 'status-active' : 'status-warning' }}">
                                        {{ $row->google2fa_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    @can('update', $row)
                                        <a href="{{ route('users.edit', $row) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    @endcan
                                    @can('delete', $row)
                                        <form action="{{ route('users.destroy', $row) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this account? Its history will be preserved.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">No manageable accounts.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
