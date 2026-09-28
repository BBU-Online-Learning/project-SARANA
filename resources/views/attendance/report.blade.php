@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Attendance report · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @php
        $breadcrumbs = [['label' => 'Attendance', 'url' => route('classes.attendance.index', $schoolClass)]];
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'attendance', 'title' => 'Attendance report', 'description' => 'Review class totals and export dated attendance.', 'breadcrumbs' => $breadcrumbs])
    @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    <form method="GET" action="{{ route('classes.attendance.report', $schoolClass) }}" class="d-flex flex-wrap gap-2 mb-3">
        <label>From <input class="form-control" type="date" name="from" value="{{ request('from') }}"></label>
        <label>To <input class="form-control" type="date" name="to" value="{{ request('to') }}"></label>
        <button class="btn btn-primary align-self-end" type="submit">Filter</button>
        <a class="btn btn-outline-primary align-self-end" href="{{ route('classes.attendance.export', [$schoolClass, 'from' => request('from'), 'to' => request('to')]) }}">Export CSV</a>
    </form>
    <div class="table-responsive"><table class="table attendance-stack-table"><thead><tr><th>Date</th><th>Students</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th><th>State</th></tr></thead><tbody>
        @forelse ($registers as $register)
            <tr><td data-label="Date"><a href="{{ route('classes.attendance.show', [$schoolClass, $register]) }}">{{ $register->attendance_date->toDateString() }}</a></td>
                <td data-label="Students">{{ $register->records_count }}</td><td data-label="Present">{{ $register->present_count }}</td><td data-label="Absent">{{ $register->absent_count }}</td><td data-label="Late">{{ $register->late_count }}</td><td data-label="Excused">{{ $register->excused_count }}</td><td data-label="State"><x-class-status :value="$register->finalized_at ? 'finalized' : 'open'" /></td></tr>
        @empty
            <tr><td colspan="7" data-label="Attendance">No attendance in this range.</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $registers->links() }}
</div>
@endsection
