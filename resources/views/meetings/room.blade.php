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
    data-end-at="{{ $meeting->ends_at->copy()->addMinutes(config('livekit.join_after_minutes'))->toIso8601String() }}"></div>
@endsection
@section('scripts')
    @vite('resources/js/meeting-room.jsx')
@endsection
