@extends('layouts.app')

@section('content')
    <div class="page-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Users</h4>
            @can('create', \App\Models\User::class)
                <a href="{{ route('users.create') }}" class="btn btn-primary">Add User</a>
            @endcan
        </div>
        <p class="text-muted">Only accounts you may manage are listed. Super Admin and legacy accounts are protected.</p>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>2FA</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse ($users as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->role->name }}</td>
                                <td>{{ $row->email }}</td>
                                <td>{{ $row->status ?: 'Legacy status (review required)' }}</td>
                                <td>
                                    <span class="badge {{ $row->google2fa_enabled ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $row->google2fa_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td>
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
