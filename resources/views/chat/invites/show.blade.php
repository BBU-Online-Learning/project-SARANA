@extends('layouts.app')

@section('title', 'Join '.$room->name)

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/group-invite.css') }}">
@endsection

@section('content')
<div class="page-container group-invite-preview">
    <article class="group-invite-preview-card">
        <div class="group-invite-preview-banner" aria-hidden="true"></div>
        <div class="group-invite-preview-body">
            <span class="group-invite-preview-avatar" aria-hidden="true">
                @if($room->avatarUrl())<img src="{{ $room->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($room->name ?: 'G', 0, 1)) }}@endif
            </span>
            <p class="workspace-eyebrow mb-2">Group chat invitation</p>
            <h1>{{ $room->name }}</h1>
            <p class="group-invite-preview-meta">{{ $room->members->count() }} {{ Str::plural('member', $room->members->count()) }} · Invitation from {{ $invite->creator?->name ?? 'the group owner' }}</p>
            @if($room->description)<p class="group-invite-preview-description">{{ $room->description }}</p>@endif
            <span class="group-invite-preview-owner"><i class="ti ti-shield-check" aria-hidden="true"></i> Secure BBU group invitation</span>

            @if($available)
                <div class="group-invite-preview-actions">
                    @if($isMember)
                        <a class="btn btn-primary" href="{{ route('chat.index', ['room' => $room->id]) }}"><i class="ti ti-message-circle" aria-hidden="true"></i> Open group chat</a>
                    @else
                        <form method="POST" action="{{ $joinUrl }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100"><i class="ti ti-user-plus" aria-hidden="true"></i> Join group chat</button>
                        </form>
                    @endif
                    <a class="btn btn-light" href="{{ route('chat.index') }}">Back to chats</a>
                </div>
            @else
                <div class="group-invite-unavailable"><i class="ti ti-link-off" aria-hidden="true"></i> This invitation has expired or was disabled by the group owner.</div>
                <div class="group-invite-preview-actions mt-3"><a class="btn btn-light" href="{{ route('chat.index') }}">Back to chats</a></div>
            @endif
        </div>
    </article>
</div>
@endsection
