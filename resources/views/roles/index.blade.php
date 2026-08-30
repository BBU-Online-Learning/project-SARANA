@extends('layouts.app')

@section('content')
    <div class="page-container">
        <h4 class="mb-3">Institution Roles</h4>
        <div class="alert alert-info">
            Roles are fixed. Super Admin manages Admin, Teacher and Student accounts.
            Admin manages Teacher and Student accounts only.
            Super Admin accounts cannot be edited or deleted through account management.
            Administrative privileges do not grant access to private conversations.
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Role</th><th>Definition</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>{{ $role->name }}</td>
                                <td>{{ in_array($role->name, \App\Models\Role::NAMES, true) ? 'Fixed institution role' : 'Legacy role (preserved; not assignable)' }}</td>
                                <td>{{ $role->trashed() ? 'Deleted (preserved)' : ($role->status ? 'Enabled' : 'Disabled') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
