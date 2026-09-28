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
                <p class="mb-0">Find and manage the accounts available to your role.</p>
            </div>
            @can('create', \App\Models\User::class)
                <a href="{{ route('users.create') }}" class="btn btn-primary workspace-heading-action"><i class="ti ti-user-plus" aria-hidden="true"></i> Add User</a>
            @endcan
        </section>
        <p class="workspace-page-note"><i class="ti ti-shield-lock" aria-hidden="true"></i><span>Only accounts you may manage are listed. Super Admin and legacy accounts are protected.</span></p>
        <form method="GET" action="{{ route('users.index') }}" class="card account-filter-card mb-3" role="search" aria-label="Find accounts">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label for="account-search" class="form-label">Name or email</label>
                        <input id="account-search" name="search" type="search" class="form-control @error('search') is-invalid @enderror" value="{{ $search }}" maxlength="100" placeholder="Search accounts">
                        @error('search') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="account-role" class="form-label">Role</label>
                        <select id="account-role" name="role" class="form-select @error('role') is-invalid @enderror">
                            <option value="">All roles</option>
                            @foreach ($names as $name)
                                <option value="{{ $name }}" @selected($role === $name)>{{ ucwords(str_replace('_', ' ', $name)) }}</option>
                            @endforeach
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="account-status" class="form-label">Status</label>
                        <select id="account-status" name="status" class="form-select @error('status') is-invalid @enderror">
                            <option value="">All statuses</option>
                            @foreach (['active', 'inactive', 'suspended'] as $state)
                                <option value="{{ $state }}" @selected($status === $state)>{{ ucfirst($state) }}</option>
                            @endforeach
                        </select>
                        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-lg-3 account-filter-actions">
                        <button class="btn btn-primary" type="submit">Apply filters</button>
                        @if ($search !== '' || $role !== '' || $status !== '')
                            <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Clear filters</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
        <p class="account-results-count">Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }} accounts</p>
        <div class="card workspace-data-card account-table-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <caption class="visually-hidden">Accounts available to manage</caption>
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
                            <tr><td colspan="6" class="text-muted">{{ $search !== '' || $role !== '' || $status !== '' ? 'No accounts match these filters. Try another search or clear filters.' : 'No manageable accounts yet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            <div class="mt-3">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
