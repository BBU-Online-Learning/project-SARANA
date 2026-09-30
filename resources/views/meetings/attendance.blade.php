@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Meeting attendance · '.$meeting->title)
@section('content')
<div class="page-container my-3">
    @include('classes.partials.section-header', ['activeSection' => 'meetings', 'title' => 'Meeting attendance', 'description' => $meeting->title, 'breadcrumbs' => [['label' => 'Meetings', 'url' => route('classes.meetings.index', $schoolClass)], ['label' => $meeting->title, 'url' => route('classes.meetings.show', [$schoolClass, $meeting])]]])
    <div class="card"><div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div><h2 class="h5 mb-1">Room attendance</h2><p class="text-muted mb-0">Time starts when LiveKit confirms a participant is active and ends when they leave or the room closes.</p></div>
            <a class="btn btn-outline-primary" href="{{ route('classes.meetings.attendance.export', [$schoolClass, $meeting]) }}">Export CSV</a>
        </div>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th scope="col">Participant</th><th scope="col">Sessions</th><th scope="col">First joined</th><th scope="col">Last left</th><th scope="col">Time in room</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr><td><strong>{{ $row['name'] }}</strong><br><span class="text-muted">{{ $row['email'] }}</span></td>
                    <td>{{ $row['sessions'] }}</td>
                    <td>{{ $row['first_joined_at']?->timezone(config('app.timezone'))->format('M j, Y g:i:s A') ?? '—' }}</td>
                    <td>{{ $row['last_left_at']?->timezone(config('app.timezone'))->format('M j, Y g:i:s A') ?? '—' }}</td>
                    <td>{{ gmdate('H:i:s', $row['total_seconds']) }}</td>
                    <td>{{ $row['in_room'] ? 'In room' : ($row['sessions'] ? 'Left' : 'Absent') }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-muted">No class students or room participants yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</div>
@endsection
