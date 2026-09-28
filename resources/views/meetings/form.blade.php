@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Schedule meeting · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @php
        $breadcrumbs = [['label' => 'Meetings', 'url' => route('classes.meetings.index', $schoolClass)]];
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'meetings', 'title' => 'Schedule a class meeting', 'description' => 'Set the time and repeat pattern for this class.', 'breadcrumbs' => $breadcrumbs])
    <p class="text-muted">Times use {{ config('app.timezone') }}. Create up to 26 fixed dates, or keep generating dates for an ongoing series.</p>
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
                    @foreach (['none' => 'Does not repeat', 'daily' => 'Daily', 'weekly' => 'Weekly', 'selected_weekdays' => 'Selected weekdays (ongoing)'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('recurrence', 'none') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('recurrence')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label class="form-label" for="meeting-count">Number of meetings</label>
                <input id="meeting-count" name="occurrence_count" type="number" min="1" max="26" class="form-control @error('occurrence_count') is-invalid @enderror" value="{{ old('occurrence_count', 1) }}">
                <div class="form-text">For fixed schedules: 1 for a single meeting or 2–26 for a repeat.</div>
                @error('occurrence_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="ongoing" value="0">
            <input id="meeting-ongoing" name="ongoing" type="checkbox" value="1" class="form-check-input @error('ongoing') is-invalid @enderror" @checked(old('ongoing'))>
            <label class="form-check-label" for="meeting-ongoing">Continue repeating beyond 26 meetings</label>
            <div class="form-text">Upcoming meetings are generated automatically about two months ahead. You can cancel a single date or the whole series.</div>
        </div>
        <fieldset class="mb-3">
            <legend class="fs-6">Weekdays for selected weekday repeats</legend>
            <div class="d-flex flex-wrap gap-3">
                @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="weekdays[]" id="meeting-day-{{ $day }}" value="{{ $day }}" @checked(in_array($day, old('weekdays', [])))><label class="form-check-label" for="meeting-day-{{ $day }}">{{ $label }}</label></div>
                @endforeach
            </div>
            @error('weekdays')<div class="text-danger small">{{ $message }}</div>@enderror
        </fieldset>
        <div class="mb-3"><label class="form-label" for="meeting-until">End repeating after this date (optional)</label>
            <input id="meeting-until" name="repeat_until" type="date" class="form-control @error('repeat_until') is-invalid @enderror" value="{{ old('repeat_until') }}">
            @error('repeat_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">Create schedule</button>
    </div></form>
</div>
@endsection
