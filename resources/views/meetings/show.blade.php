@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', $meeting->title.' · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @php
        $breadcrumbs = [['label' => 'Meetings', 'url' => route('classes.meetings.index', $schoolClass)]];
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'meetings', 'title' => $meeting->title, 'description' => 'Schedule and status for this class meeting.', 'breadcrumbs' => $breadcrumbs])
    <div class="card mb-3"><div class="card-body">
        <h2 class="visually-hidden">Meeting details</h2>
        <p class="mb-1">{{ $meeting->starts_at->format('l, F j, Y g:i A') }} to {{ $meeting->ends_at->format('l, F j, Y g:i A') }} · {{ config('app.timezone') }}</p>
        <p class="mb-2">Status: <x-class-status :value="$meeting->status" /> @if ($meeting->rescheduled_at)<x-class-status value="rescheduled" />@endif</p>
        @if ($meeting->occurrence_count > 1)
            <p class="mb-1">{{ ucfirst($meeting->recurrence) }} meeting {{ $meeting->occurrence_number }} of {{ $meeting->occurrence_count }}</p>
        @endif
        @if ($meeting->rescheduled_at)
            <p class="mb-1">Rescheduled from {{ $meeting->original_starts_at->format('M j, Y g:i A') }} by {{ $meeting->rescheduler?->name ?? 'a teacher' }}.</p>
        @endif
        @if ($meeting->cancelled_at)
            <p class="mb-1">Cancelled {{ $meeting->cancelled_at->format('M j, Y g:i A') }} by {{ $meeting->canceller?->name ?? 'a teacher' }}.</p>
        @endif
        @if ($meeting->description)<p class="mt-3 mb-0" style="white-space: pre-wrap">{{ $meeting->description }}</p>@endif
    </div></div>
    <p class="alert alert-info">Online joining is not available for class meetings yet.</p>
    @can('update', $meeting)
        <form method="POST" action="{{ route('classes.meetings.update', [$schoolClass, $meeting]) }}" class="card mb-3"><div class="card-body">
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
</div>
@endsection
