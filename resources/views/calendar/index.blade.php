@extends('layouts.app')
@section('title', 'Calendar')
@section('content')
<div class="page-container">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
        <div>
            <h1 class="h3 mb-1">Calendar</h1>
            <p class="text-muted mb-0">Quiz and coursework deadlines, and class meetings · {{ $timezone }} time</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-primary" href="{{ route('calendar.index', ['month' => $month->subMonth()->format('Y-m')]) }}" aria-label="Previous month" data-workspace-nav>←</a>
            <strong>{{ $month->format('F Y') }}</strong>
            <a class="btn btn-outline-primary" href="{{ route('calendar.index', ['month' => $month->addMonth()->format('Y-m')]) }}" aria-label="Next month" data-workspace-nav>→</a>
        </div>
    </div>

    @if ($limited)
        <div class="alert alert-info">This month has many events. The calendar shows the first 250 of each type.</div>
    @endif

    <div class="calendar-legend" aria-label="Calendar event types">
        <span class="calendar-type">Quiz deadline</span>
        <span class="calendar-type calendar-type--coursework">Coursework deadline</span>
        <span class="calendar-type calendar-type--meeting">Class meeting</span>
    </div>

    <div class="table-responsive calendar-month-grid">
        <table class="table table-bordered align-top" aria-label="Deadline calendar for {{ $month->format('F Y') }}">
            <thead><tr>
                @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $weekday)
                    <th scope="col">{{ $weekday }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach ($weeks as $week)
                    <tr>
                        @foreach ($week as $day)
                            <td class="align-top {{ $day->month === $month->month ? '' : 'text-muted bg-light' }}">
                                <span class="fw-semibold" @if ($day->isToday()) aria-current="date" @endif>{{ $day->day }}</span>
                                @foreach ($eventsByDay->get($day->toDateString(), collect()) as $event)
                                    @php($eventTone = str_starts_with($event['id'], 'coursework:') ? 'coursework' : (str_starts_with($event['id'], 'meeting:') ? 'meeting' : 'quiz'))
                                    <div class="border rounded p-2 mt-2">
                                        <small class="d-block text-muted mb-1">{{ $event['at']->format('g:i A') }}</small>
                                        <span class="calendar-type calendar-type--{{ $eventTone }}">{{ $event['type'] }}</span>
                                        <a href="{{ $event['url'] }}">{{ $event['title'] }}</a>
                                        <small class="d-block">{{ $event['class_name'] }}</small>
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <section class="calendar-agenda" aria-label="Agenda for {{ $month->format('F Y') }}">
        @foreach ($eventsByDay as $date => $events)
            <section class="calendar-agenda-day" aria-labelledby="calendar-agenda-day-{{ $date }}">
                <h2 class="calendar-agenda-date" id="calendar-agenda-day-{{ $date }}">
                    <time datetime="{{ $date }}" @if ($events->first()['at']->isToday()) aria-current="date" @endif>{{ $events->first()['at']->format('l, F j') }}</time>
                    <span class="calendar-agenda-count">{{ $events->count() }} {{ $events->count() === 1 ? 'event' : 'events' }}</span>
                </h2>
                <ol class="calendar-agenda-list">
                    @foreach ($events as $event)
                        @php($eventTone = str_starts_with($event['id'], 'coursework:') ? 'coursework' : (str_starts_with($event['id'], 'meeting:') ? 'meeting' : 'quiz'))
                        <li class="calendar-agenda-event">
                            <div class="calendar-agenda-meta">
                                <time datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->format('g:i A') }}</time>
                                <span class="calendar-type calendar-type--{{ $eventTone }}">{{ $event['type'] }}</span>
                            </div>
                            <a href="{{ $event['url'] }}">{{ $event['title'] }}</a>
                            <span class="calendar-agenda-class">{{ $event['class_name'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </section>

    @if ($eventsByDay->isEmpty())
        <p class="text-muted">No deadlines or meetings are available to you this month.</p>
    @endif
</div>
@endsection
