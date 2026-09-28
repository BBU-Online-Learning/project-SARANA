@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'My attendance')
@section('content')
<div class="page-container my-3">
    <nav aria-label="Breadcrumb" class="class-section-breadcrumb"><ol class="breadcrumb mb-3">
        <li class="breadcrumb-item"><a href="{{ route('classes.index') }}">Classes</a></li>
        <li class="breadcrumb-item active" aria-current="page">My attendance</li>
    </ol></nav>
    <header class="card class-hero class-section-hero mb-3"><div class="card-body">
        <span class="badge class-hero-badge mb-2">Attendance</span>
        <h1 class="class-title h3 mb-2">My attendance</h1>
        <p class="class-description mb-0">Finalized attendance from your current and former classes.</p>
    </div></header>
    <div class="table-responsive"><table class="table attendance-stack-table"><thead><tr><th>Class</th><th>Date</th><th>Status</th><th>Note</th></tr></thead><tbody>
        @forelse ($records as $record)
            <tr><td data-label="Class">{{ $record->register->schoolClass?->name ?? 'Former class' }}</td>
                <td data-label="Date"><a href="{{ route('classes.attendance.show', [$record->register->school_class_id, $record->register]) }}">{{ $record->register->attendance_date->toDateString() }}</a></td>
                <td data-label="Status"><x-class-status :value="$record->status" /></td><td data-label="Note">{{ $record->note ?: '—' }}</td></tr>
        @empty
            <tr><td colspan="4" data-label="Attendance">No finalized attendance records yet.</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $records->links() }}
</div>
@endsection
