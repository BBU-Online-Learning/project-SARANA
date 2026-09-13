@extends('layouts.app')
@section('title', 'User profile')
@section('content')
<div class="page-container workspace-page profile-page">
    <a href="{{ route('home') }}" class="workspace-class-back"><i class="ti ti-arrow-left" aria-hidden="true"></i> Back to workspace</a>
    <section class="profile-hero card mb-3">
        <div class="card-body d-flex flex-column flex-sm-row align-items-sm-center gap-3">
            <x-user-avatar :user="$user" :size="112" />
            <div class="min-w-0">
                <p class="workspace-eyebrow mb-1">User profile</p>
                <h1 class="h3 mb-1 text-break">{{ $user->name }}</h1>
                <p class="mb-1">{{ ucwords(str_replace('_', ' ', $user->role->name)) }}</p>
                <p class="text-muted mb-0">
                    {{ $user->last_seen_at ? 'Last seen '.$user->last_seen_at->diffForHumans() : 'Offline' }}
                </p>
            </div>
            @if (auth()->user()->is($user))
                <a href="{{ route('profile.edit') }}" class="btn btn-primary ms-sm-auto">Edit profile</a>
            @endif
        </div>
    </section>
    <div class="row g-3">
        <section class="col-lg-7"><div class="card h-100"><div class="card-body">
            <h2 class="h5">About</h2>
            <p class="profile-bio mb-0">{{ $user->bio ?: 'No bio has been added yet.' }}</p>
        </div></div></section>
        <section class="col-lg-5"><div class="card h-100"><div class="card-body">
            <h2 class="h5">Profile information</h2>
            <dl class="mb-0"><dt>Role</dt><dd>{{ ucwords(str_replace('_', ' ', $user->role->name)) }}</dd>
                <dt>Member since</dt><dd>{{ $user->created_at->format('F Y') }}</dd></dl>
            @if ($sharedClasses->isNotEmpty())
                <h3 class="h6 mt-3">Classes you share</h3>
                <ul class="mb-0">@foreach ($sharedClasses as $schoolClass)<li><a href="{{ route('classes.show', $schoolClass) }}">{{ $schoolClass->name }}</a></li>@endforeach</ul>
            @endif
        </div></div></section>
    </div>
</div>
@endsection
