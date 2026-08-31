@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-container workspace-page">
    <div class="card mb-4">
        <div class="card-body">
            <p class="text-muted mb-1">{{ ucwords(str_replace('_', ' ', $user->role->name)) }} workspace</p>
            <h1 class="h3">Welcome, {{ $user->name }}</h1>
            <p class="mb-0">{{ $administrator ? 'Manage permitted institution accounts and class spaces. Private conversations remain membership-only.' : 'Your classes and conversations, together in one place.' }}</p>
        </div>
    </div>
    <div class="row g-3 mb-4" aria-label="Workspace counts">
        <div class="col-6 col-lg-3"><a class="card workspace-count" href="{{ route('classes.index') }}"><div class="card-body"><span>{{ $administrator ? 'Active institution classes' : 'My active classes' }}</span><strong data-count="active-classes">{{ $classCounts['active'] }}</strong></div></a></div>
        <div class="col-6 col-lg-3"><a class="card workspace-count" href="{{ route('classes.index') }}"><div class="card-body"><span>{{ $administrator ? 'Archived institution classes' : 'My archived classes' }}</span><strong data-count="archived-classes">{{ $classCounts['archived'] }}</strong></div></a></div>
        <div class="col-6 col-lg-3"><a class="card workspace-count" href="{{ route('chat.index') }}"><div class="card-body"><span>My direct conversations</span><strong data-count="direct-rooms">{{ $conversationCounts['direct'] }}</strong></div></a></div>
        <div class="col-6 col-lg-3"><a class="card workspace-count" href="{{ route('chat.index') }}"><div class="card-body"><span>My group conversations</span><strong data-count="group-rooms">{{ $conversationCounts['group'] }}</strong></div></a></div>
    </div>
    @if($accountCounts)
        <section class="card mb-4" aria-labelledby="account-heading"><div class="card-body">
            <h2 class="h5" id="account-heading">{{ $user->role->name === 'super_admin' ? 'Privileged account administration' : 'Teacher and student administration' }}</h2>
            <p class="text-muted">Current accounts you may manage, including suspended accounts. Deleted accounts are excluded.</p>
            <dl class="row">
                @foreach($accountCounts as $role => $count)
                    <div class="col-sm-4"><dt>{{ ucfirst($role) }} accounts</dt><dd class="h3" data-count="{{ $role }}-accounts">{{ $count }}</dd></div>
                @endforeach
            </dl>
            <a class="btn btn-primary" href="{{ route('users.index') }}">Manage accounts</a>
            <a class="btn btn-outline-secondary" href="{{ route('classes.index') }}">Class administration</a>
        </div></section>
    @endif
    <section class="card mb-4" aria-labelledby="class-heading"><div class="card-body">
        <h2 class="h5" id="class-heading">{{ $administrator ? 'Recently updated classes' : 'My classes' }}</h2>
        <ul class="list-group list-group-flush">
            @forelse($recentClasses as $schoolClass)
                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                    <a href="{{ route('classes.show', $schoolClass) }}">{{ $schoolClass->name }}</a>
                    <span class="badge {{ $schoolClass->isArchived() ? 'bg-secondary' : 'bg-success' }}">{{ $schoolClass->isArchived() ? 'Archived' : 'Active' }}</span>
                </li>
            @empty
                <li class="list-group-item text-muted">No classes yet. Open Classes to {{ $user->can('manage-classes') ? 'create or join a class' : 'join with a class code' }}.</li>
            @endforelse
        </ul>
        <a class="btn btn-outline-primary mt-3" href="{{ route('classes.index') }}">Open classes</a>
    </div></section>
    <section class="card"><div class="card-body">
        <h2 class="h5">Conversations</h2>
        <p>{{ array_sum($conversationCounts) === 0 ? 'No conversations yet. Start a direct chat or create a group.' : 'Open Chats to continue your direct and group conversations.' }}</p>
        <a class="btn btn-primary" href="{{ route('chat.index') }}">Open chats</a>
    </div></section>
</div>
@endsection
