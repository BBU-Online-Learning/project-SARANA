@extends('layouts.app')

@section('content')
<div class="page-container" style="height: calc(100vh - 100px); overflow-y: auto;">
    <div class="card">
        <div class="card-body">
            <a href="{{ route('chat.index') }}" class="btn btn-outline-secondary mb-3">Back to Chats</a>
            <h4>{{ $room->name }}</h4>
            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif
            @unless($owner)
                <div class="alert alert-warning">Group ownership needs review. No member will be automatically promoted.</div>
            @endunless
            @can('manageGroup', $room)
                <form method="POST" action="{{ route('chat.groups.update', $room) }}" class="mb-4">
                    @csrf @method('PATCH')
                    <label for="group-name" class="form-label">Group name</label>
                    <input id="group-name" name="name" required minlength="3" maxlength="100" class="form-control mb-2" value="{{ is_string(old('name')) ? old('name') : $room->name }}">
                    <button class="btn btn-primary">Rename group</button>
                </form>
                <form method="POST" action="{{ route('chat.groups.members.store', $room) }}" class="mb-4">
                    @csrf
                    <label for="group-members" class="form-label">Add active members</label>
                    <select id="group-members" name="members[]" multiple required class="form-select mb-2">
                        @foreach($users as $user) <option value="{{ $user->id }}">{{ $user->name }}</option> @endforeach
                    </select>
                    <button class="btn btn-primary" @disabled($users->isEmpty())>Add members</button>
                </form>
            @endcan
            <h5>Members</h5>
            <ul class="list-group mb-4">
                @foreach($room->members as $member)
                    <li class="list-group-item d-flex align-items-center justify-content-between">
                        <span>{{ $member->name }} @if($member->pivot->role === 'owner') <span class="badge bg-primary">Owner</span> @endif</span>
                        @can('manageGroup', $room)
                            @if($member->pivot->role !== 'owner')
                                <form method="POST" action="{{ route('chat.groups.members.destroy', [$room, $member]) }}">
                                    @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            @endif
                        @endcan
                    </li>
                @endforeach
            </ul>
            @if($room->roomMembers()->where('user_id', auth()->id())->value('role') !== 'owner')
                <form method="POST" action="{{ route('chat.groups.leave', $room) }}">
                    @csrf <button class="btn btn-outline-danger">Leave group</button>
                </form>
            @else
                <p class="text-muted">The owner must remain in the group.</p>
            @endif
        </div>
    </div>
</div>
@endsection
