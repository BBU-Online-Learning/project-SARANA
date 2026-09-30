@extends('layouts.app')
@section('bodyClass', 'class-page workspace-full-page meeting-room-page')
@section('title', 'Meeting room · '.$meeting->title)
@section('content')
<div id="meeting-react-root"
    data-meeting-title="{{ $meeting->title }}"
    data-class-name="{{ $schoolClass->name }}"
    data-start-label="{{ $meeting->starts_at->format('D, M j · g:i A') }}"
    data-back-url="{{ route('classes.meetings.show', [$schoolClass, $meeting]) }}"
    data-credentials-url="{{ route('classes.meetings.credentials', [$schoolClass, $meeting]) }}"
    data-can-manage="{{ \Illuminate\Support\Facades\Gate::allows('manageJoinRequests', $meeting) ? 'true' : 'false' }}"
    data-waiting-room-url="{{ route('classes.meetings.waiting-room.show', [$schoolClass, $meeting]) }}"
    data-join-requests-url="{{ route('classes.meetings.join-requests.index', [$schoolClass, $meeting]) }}"
    data-end-url="{{ route('classes.meetings.end', [$schoolClass, $meeting]) }}"
    data-remove-url-template="{{ route('classes.meetings.participants.remove', [$schoolClass, $meeting, '__USER__']) }}"
    data-removable-user-ids="{{ implode(',', $removableUserIds) }}"
    data-can-end="{{ \Illuminate\Support\Facades\Gate::allows('end', $meeting) ? 'true' : 'false' }}"
    data-end-at="{{ $meeting->ends_at->copy()->addMinutes(config('livekit.join_after_minutes'))->toIso8601String() }}"></div>
@endsection
@section('scripts')
    @vite('resources/js/meeting-room.jsx')
@endsection
