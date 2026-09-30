@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', $meeting->title.' · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @php
        $breadcrumbs = [['label' => 'Meetings', 'url' => route('classes.meetings.index', $schoolClass)]];
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'meetings', 'title' => $meeting->title, 'description' => 'Schedule and status for this class meeting.', 'breadcrumbs' => $breadcrumbs])
    <div class="meeting-detail-grid mb-4">
        <section class="card meeting-detail-main" aria-labelledby="meeting-overview-title"><div class="card-body">
            <div class="meeting-detail-eyebrow"><i class="ti ti-video" aria-hidden="true"></i> Class meeting</div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><x-class-status :value="$meeting->status" /> @if ($meeting->rescheduled_at)<x-class-status value="rescheduled" />@endif</div>
            <h2 id="meeting-overview-title" class="h4 mb-3">When we meet</h2>
            <div class="meeting-schedule-panel">
                <div class="meeting-date-tile" aria-hidden="true"><span>{{ $meeting->starts_at->format('M') }}</span><strong>{{ $meeting->starts_at->format('j') }}</strong></div>
                <div><strong>{{ $meeting->starts_at->format('l, F j, Y') }}</strong><span>{{ $meeting->starts_at->format('g:i A') }}–{{ $meeting->ends_at->isSameDay($meeting->starts_at) ? $meeting->ends_at->format('g:i A') : $meeting->ends_at->format('l, F j, Y g:i A') }} · {{ config('app.timezone') }}</span></div>
            </div>
            @if ($meeting->description)
                <h3 class="h6 mt-4">About this meeting</h3>
                <p class="meeting-description mb-0">{{ $meeting->description }}</p>
            @endif
            @if ($meeting->rescheduled_at)
                <p class="meeting-detail-note">Rescheduled from {{ $meeting->original_starts_at->format('M j, Y g:i A') }} by {{ $meeting->rescheduler?->name ?? 'a teacher' }}.</p>
            @endif
            @if ($meeting->cancelled_at)
                <p class="meeting-detail-note">Cancelled {{ $meeting->cancelled_at->format('M j, Y g:i A') }} by {{ $meeting->canceller?->name ?? 'a teacher' }}.</p>
            @endif
            @if ($meeting->ended_at)
                <p class="meeting-detail-note">Ended for everyone {{ $meeting->ended_at->format('M j, Y g:i A') }} by {{ $meeting->ender?->name ?? 'a teacher' }}.</p>
            @endif
        </div></section>
        <aside class="meeting-detail-side">
            <div class="card meeting-join-panel mb-3"><div class="card-body">
                <div class="meeting-join-icon"><i class="ti ti-video" aria-hidden="true"></i></div>
                <h2 class="h5">Meeting room</h2>
                @if ($joinAvailable)
                    <p>The room is open. Join when you are ready.</p>
                    <a class="btn btn-primary w-100" href="{{ route('classes.meetings.room', [$schoolClass, $meeting]) }}"><i class="ti ti-video" aria-hidden="true"></i> Join meeting</a>
                @elseif (! $videoConfigured)
                    <p>Online joining is not available until a LiveKit meeting service is configured.</p>
                @elseif ($meeting->status === 'scheduled')
                    <p>Join opens 15 minutes before the scheduled start and closes 15 minutes after the scheduled end.</p>
                @else
                    <p>This meeting room is closed.</p>
                @endif
            </div></div>
            <div class="card meeting-context-panel"><div class="card-body">
                <h2 class="h6">Meeting details</h2>
                <dl>
                    <div><dt>Class</dt><dd>{{ $schoolClass->name }}</dd></div>
                    <div><dt>Host</dt><dd>{{ $meeting->creator?->name ?? 'Class teacher' }}</dd></div>
                    @if ($meeting->series)
                        <div><dt>Repeat</dt><dd>Ongoing {{ str_replace('_', ' ', $meeting->series->recurrence) }} series · occurrence {{ $meeting->occurrence_number }}@if ($meeting->series->ends_on) · repeats until {{ $meeting->series->ends_on->format('M j, Y') }}@endif</dd></div>
                    @elseif ($meeting->occurrence_count > 1)
                        <div><dt>Repeat</dt><dd>{{ ucfirst($meeting->recurrence) }} meeting {{ $meeting->occurrence_number }} of {{ $meeting->occurrence_count }}</dd></div>
                    @endif
                </dl>
            </div></div>
        </aside>
    </div>
    @can('update', $meeting)
        <form method="POST" action="{{ route('classes.meetings.update', [$schoolClass, $meeting]) }}" class="card meeting-edit-panel mb-3"><div class="card-body">
            @csrf @method('PATCH')
            <h2 class="h5">Edit this occurrence</h2>
            <p class="text-muted">Changes here affect this meeting only. Other meetings in the repeat keep their dates.</p>
            <div class="mb-3"><label for="meeting-title" class="form-label">Title</label>
                <input id="meeting-title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $meeting->title) }}" maxlength="180" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3"><label for="meeting-description" class="form-label">Description</label>
                <textarea id="meeting-description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3" maxlength="5000">{{ old('description', $meeting->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label for="meeting-start" class="form-label">Starts at</label>
                    <input id="meeting-start" name="starts_at" type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', $meeting->starts_at->format('Y-m-d\TH:i')) }}" required>
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6"><label for="meeting-end" class="form-label">Ends at</label>
                    <input id="meeting-end" name="ends_at" type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', $meeting->ends_at->format('Y-m-d\TH:i')) }}" required>
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div></form>
        <form method="POST" action="{{ route('classes.meetings.cancel', [$schoolClass, $meeting]) }}" data-confirm-title="Cancel this meeting?" data-confirm-message="This occurrence will be marked cancelled for the class." data-confirm-label="Cancel meeting">
            @csrf
            <button type="submit" class="btn btn-outline-danger">Cancel this meeting</button>
        </form>
    @endcan
    @can('end', $meeting)
        <form method="POST" action="{{ route('classes.meetings.end', [$schoolClass, $meeting]) }}" class="mt-2" data-confirm-title="End this meeting for everyone?" data-confirm-message="Everyone in the LiveKit room will be disconnected." data-confirm-label="End meeting">
            @csrf
            <button type="submit" class="btn btn-outline-danger">{{ $meeting->status === 'ending' ? 'Retry ending the meeting' : 'End meeting for everyone' }}</button>
        </form>
    @endcan
    @if ($meeting->series?->status === 'active')
        @can('create', [\App\Models\ClassMeeting::class, $schoolClass])
            <form method="POST" action="{{ route('classes.meetings.series.cancel', [$schoolClass, $meeting->series]) }}" class="mt-2" data-confirm-title="Cancel all future meetings?" data-confirm-message="Future scheduled dates in this series will be cancelled." data-confirm-label="Cancel series">
                @csrf
                <button type="submit" class="btn btn-outline-danger">Cancel future meetings in this series</button>
            </form>
        @endcan
    @endif
</div>
@endsection
