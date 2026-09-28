@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Meetings · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @php($timezone = config('app.timezone'))
    @include('classes.partials.section-header', ['activeSection' => 'meetings', 'title' => 'Class meetings', 'description' => 'Class schedule · '.$timezone.' time', 'showSectionActions' => true])
    <p class="alert alert-info">These are scheduled class meetings. Online joining is not available here yet.</p>
    @forelse ($meetings as $meeting)
        <article class="card class-list-card meeting-card {{ $meeting->status === 'cancelled' ? 'is-cancelled' : '' }} mb-3"><div class="card-body">
            <div class="d-flex flex-wrap align-items-start gap-3">
                <div class="meeting-date-tile" aria-hidden="true"><span>{{ $meeting->starts_at->format('M') }}</span><strong>{{ $meeting->starts_at->format('j') }}</strong></div>
                <div class="flex-grow-1">
                    <h2 class="h5 mb-2"><a href="{{ route('classes.meetings.show', [$schoolClass, $meeting]) }}">{{ $meeting->title }}</a></h2>
                    <p class="mb-1"><i class="ti ti-clock" aria-hidden="true"></i> {{ $meeting->starts_at->format('D, M j, Y g:i A') }}–{{ $meeting->ends_at->isSameDay($meeting->starts_at) ? $meeting->ends_at->format('g:i A') : $meeting->ends_at->format('D, M j, Y g:i A') }} {{ $timezone }}</p>
                    @if ($meeting->occurrence_count > 1)
                        <small class="text-muted">{{ ucfirst($meeting->recurrence) }} · Meeting {{ $meeting->occurrence_number }} of {{ $meeting->occurrence_count }}</small>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <x-class-status :value="$meeting->status" />
                    @if ($meeting->rescheduled_at)<x-class-status value="rescheduled" />@endif
                </div>
            </div>
        </div></article>
    @empty
        <x-class-empty title="No meetings have been scheduled for this class." description="New class meetings will appear here with their dates and status." icon="ti-calendar-event" />
    @endforelse
    {{ $meetings->links() }}
</div>
@endsection
