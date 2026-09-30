@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Meetings · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @php
        $timezone = config('app.timezone');
        $currentMeetings = $meetings->getCollection()->filter(fn ($meeting) => in_array($meeting->id, $joinableMeetingIds));
        $upcomingMeetings = $meetings->getCollection()->filter(fn ($meeting) => $meeting->status === 'scheduled' && $meeting->ends_at->isFuture() && ! in_array($meeting->id, $joinableMeetingIds));
        $earlierMeetings = $meetings->getCollection()->filter(fn ($meeting) => $meeting->status !== 'scheduled' || $meeting->ends_at->isPast());
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'meetings', 'title' => 'Class meetings', 'description' => 'Join live lessons, see what is next, and review earlier meetings.', 'showSectionActions' => true])

    @if ($meetings->isEmpty())
        <x-class-empty title="No meetings have been scheduled for this class." description="New class meetings will appear here with their dates and status." icon="ti-calendar-event" />
    @else
        @foreach ([['title' => 'Ready to join', 'description' => 'The room is open now.', 'items' => $currentMeetings, 'icon' => 'ti-video'], ['title' => 'Coming up', 'description' => 'Your next scheduled class sessions.', 'items' => $upcomingMeetings, 'icon' => 'ti-calendar-event'], ['title' => 'Earlier meetings', 'description' => 'Past and cancelled sessions.', 'items' => $earlierMeetings, 'icon' => 'ti-history']] as $group)
            @if ($group['items']->isNotEmpty())
                <section class="meeting-list-section mb-4" aria-label="{{ $group['title'] }}">
                    <div class="meeting-list-heading">
                        <div class="meeting-list-heading-icon"><i class="ti {{ $group['icon'] }}" aria-hidden="true"></i></div>
                        <div><h2 class="h5 mb-1">{{ $group['title'] }}</h2><p class="text-muted mb-0">{{ $group['description'] }}</p></div>
                    </div>
                    <div class="meeting-card-grid">
                        @foreach ($group['items'] as $meeting)
                            <article class="card class-list-card meeting-card {{ $meeting->status === 'cancelled' ? 'is-cancelled' : '' }}">
                                <div class="card-body">
                                    <div class="meeting-card-top">
                                        <div class="meeting-date-tile" aria-hidden="true"><span>{{ $meeting->starts_at->format('M') }}</span><strong>{{ $meeting->starts_at->format('j') }}</strong></div>
                                        <div class="d-flex flex-wrap gap-1 justify-content-end"><x-class-status :value="$meeting->status" />@if ($meeting->rescheduled_at)<x-class-status value="rescheduled" />@endif</div>
                                    </div>
                                    <h3 class="meeting-card-title"><a href="{{ route('classes.meetings.show', [$schoolClass, $meeting]) }}">{{ $meeting->title }}</a></h3>
                                    <p class="meeting-card-meta"><i class="ti ti-clock" aria-hidden="true"></i> {{ $meeting->starts_at->format('D, M j, Y · g:i A') }}–{{ $meeting->ends_at->isSameDay($meeting->starts_at) ? $meeting->ends_at->format('g:i A') : $meeting->ends_at->format('D, M j, Y g:i A') }} {{ $timezone }}</p>
                                    <p class="meeting-card-meta"><i class="ti ti-user" aria-hidden="true"></i> {{ $meeting->creator?->name ?? 'Class teacher' }}</p>
                                    @if ($meeting->series)
                                        <p class="meeting-card-meta"><i class="ti ti-repeat" aria-hidden="true"></i> Ongoing {{ str_replace('_', ' ', $meeting->series->recurrence) }} · Meeting {{ $meeting->occurrence_number }}</p>
                                    @elseif ($meeting->occurrence_count > 1)
                                        <p class="meeting-card-meta"><i class="ti ti-repeat" aria-hidden="true"></i> {{ ucfirst($meeting->recurrence) }} · Meeting {{ $meeting->occurrence_number }} of {{ $meeting->occurrence_count }}</p>
                                    @endif
                                    <div class="meeting-card-actions">
                                        <a class="btn btn-outline-primary" href="{{ route('classes.meetings.show', [$schoolClass, $meeting]) }}">View details <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                                        @if (in_array($meeting->id, $joinableMeetingIds))
                                            <a class="btn btn-primary" href="{{ route('classes.meetings.room', [$schoolClass, $meeting]) }}"><i class="ti ti-video" aria-hidden="true"></i> Join meeting</a>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    @endif
    {{ $meetings->links() }}
</div>
@endsection
