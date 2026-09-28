@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Attendance · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @include('classes.partials.section-header', ['activeSection' => 'attendance', 'title' => 'Attendance', 'description' => $canReport ? 'Open and review dated class registers.' : 'View your finalized attendance for this class.', 'showSectionActions' => true])
    @if (session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    @if ($canReport)
        @can('create', [\App\Models\ClassAttendanceRegister::class, $schoolClass])
            <form id="attendance-open-form" method="POST" action="{{ route('classes.attendance.open', $schoolClass) }}" class="card card-body mb-3">
                @csrf
                <label for="attendance_date" class="form-label">Attendance date</label>
                <input id="attendance_date" class="form-control mb-2" type="date" name="attendance_date" max="{{ now()->toDateString() }}" value="{{ old('attendance_date', now()->toDateString()) }}" required>
                <button class="btn btn-primary" type="submit">Open dated roster</button>
            </form>
        @endcan
    @endif
    <section class="card card-body" aria-labelledby="attendance-registers-heading">
        <h2 class="h5" id="attendance-registers-heading">Registers</h2>
        @forelse ($registers as $register)
            <article class="class-list-card d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 mb-2">
                <div>
                    <h3 class="h6 mb-1"><a href="{{ route('classes.attendance.show', [$schoolClass, $register]) }}">{{ $register->attendance_date->format('d M Y') }}</a></h3>
                    @if ($canReport) <span class="small text-muted">{{ $register->records_count }} students</span> @endif
                </div>
                <x-class-status :value="$register->finalized_at ? 'finalized' : ($register->reviewed_at ? 'reviewed' : 'open')" />
            </article>
        @empty
            <x-class-empty title="No attendance registers available." description="Dated registers will appear here when your class has attendance records." icon="ti-clipboard-list" />
        @endforelse
        {{ $registers->links() }}
    </section>
</div>
@endsection
