@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Meeting room · '.$meeting->title)
@section('content')
<div class="page-container my-3">
    @php($breadcrumbs = [['label' => 'Meetings', 'url' => route('classes.meetings.index', $schoolClass)], ['label' => $meeting->title, 'url' => route('classes.meetings.show', [$schoolClass, $meeting])]])
    @include('classes.partials.section-header', ['activeSection' => 'meetings', 'title' => $meeting->title, 'description' => 'Live class meeting · '.config('app.timezone').' time', 'breadcrumbs' => $breadcrumbs])
    <section id="class-meeting-room" class="card" aria-label="Live meeting room"
        data-credentials-url="{{ route('classes.meetings.credentials', [$schoolClass, $meeting]) }}"
        data-end-at="{{ $meeting->ends_at->copy()->addMinutes(15)->toIso8601String() }}">
        <div class="card-body">
            <div class="meeting-room-heading">
                <div>
                    <h2 class="h5 mb-1">Live classroom</h2>
                    <p id="meeting-room-status" class="mb-0" role="status" aria-live="polite">Ready to connect. Your microphone and camera will start off.</p>
                </div>
                <span id="meeting-participant-count" class="meeting-participant-count" aria-live="polite">0 participants</span>
            </div>
            <div class="meeting-controls" aria-label="Meeting controls">
                <button id="meeting-connect" type="button" class="btn btn-primary meeting-control meeting-control-connect">Connect to meeting</button>
                <button id="meeting-microphone" type="button" class="btn btn-outline-primary meeting-control" aria-pressed="false" disabled>Turn on microphone</button>
                <button id="meeting-camera" type="button" class="btn btn-outline-primary meeting-control" aria-pressed="false" disabled>Turn on camera</button>
                <button id="meeting-screen" type="button" class="btn btn-outline-primary meeting-control" aria-pressed="false" disabled>Share screen</button>
                <button id="meeting-sound" type="button" class="btn btn-outline-primary meeting-control" disabled>Enable sound</button>
                <button id="meeting-leave" type="button" class="btn btn-outline-danger meeting-control meeting-control-leave" disabled>Leave meeting</button>
            </div>
            <details id="meeting-device-settings" class="meeting-device-settings">
                <summary>Choose microphone or camera</summary>
                <div class="meeting-device-grid">
                    <div>
                        <label class="form-label" for="meeting-microphone-device">Microphone</label>
                        <select id="meeting-microphone-device" class="form-select" disabled><option>Connect to choose a device</option></select>
                    </div>
                    <div>
                        <label class="form-label" for="meeting-camera-device">Camera</label>
                        <select id="meeting-camera-device" class="form-select" disabled><option>Connect to choose a device</option></select>
                    </div>
                </div>
            </details>
            <div id="meeting-participants" class="row g-3" aria-label="Meeting participants"></div>
        </div>
    </section>
    <p class="small text-muted mt-3">Allow your browser to use the microphone or camera when you turn them on. Joining does not mark classroom attendance automatically.</p>
</div>
@endsection
@section('scripts')
    @vite('resources/js/meeting-room.js')
@endsection
