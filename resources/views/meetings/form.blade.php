@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Schedule meeting · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @php
        $breadcrumbs = [['label' => 'Meetings', 'url' => route('classes.meetings.index', $schoolClass)]];
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'meetings', 'title' => 'Schedule a class meeting', 'description' => 'Set the time and repeat pattern for this class.', 'breadcrumbs' => $breadcrumbs])
    <p class="text-muted">Times use {{ config('app.timezone') }}. Repeats create a fixed set of daily or weekly meetings, up to 26 occurrences.</p>
    <p class="alert alert-info">Online joining is not available for class meetings yet.</p>
    <form method="POST" action="{{ route('classes.meetings.store', $schoolClass) }}" class="card"><div class="card-body">
        @csrf
        <div class="mb-3"><label class="form-label" for="meeting-title">Title</label>
            <input id="meeting-title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" maxlength="180" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3"><label class="form-label" for="meeting-description">Description</label>
            <textarea id="meeting-description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3" maxlength="5000">{{ old('description') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label" for="meeting-start">Starts at</label>
                <input id="meeting-start" name="starts_at" type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', now()->addDay()->format('Y-m-d\TH:i')) }}" required>
                @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label class="form-label" for="meeting-end">Ends at</label>
                <input id="meeting-end" name="ends_at" type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', now()->addDay()->addHour()->format('Y-m-d\TH:i')) }}" required>
                @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label" for="meeting-recurrence">Repeat</label>
                <select id="meeting-recurrence" name="recurrence" class="form-select @error('recurrence') is-invalid @enderror">
                    @foreach (['none' => 'Does not repeat', 'daily' => 'Daily', 'weekly' => 'Weekly'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('recurrence', 'none') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('recurrence')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label class="form-label" for="meeting-count">Number of meetings</label>
                <input id="meeting-count" name="occurrence_count" type="number" min="1" max="26" class="form-control @error('occurrence_count') is-invalid @enderror" value="{{ old('occurrence_count', 1) }}" required>
                <div class="form-text">Use 1 for a single meeting or 2–26 for a repeat.</div>
                @error('occurrence_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Create schedule</button>
    </div></form>
</div>
@endsection
