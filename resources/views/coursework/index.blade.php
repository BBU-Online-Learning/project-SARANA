@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Coursework · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @include('classes.partials.section-header', ['activeSection' => 'coursework', 'title' => 'Coursework', 'description' => 'Written assignments and private submissions for this class.', 'showSectionActions' => true])
    @forelse ($assignments as $assignment)
        <article class="card class-list-card mb-3"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h2><a href="{{ route('classes.coursework.assignments.show', [$schoolClass, $assignment]) }}">{{ $assignment->title }}</a></h2>
                        <x-class-status :value="$assignment->status" />
                    </div>
                    <div class="class-list-meta">
                        <span><i class="ti ti-book" aria-hidden="true"></i>{{ $assignment->subject?->name ?? 'General' }}</span>
                        <span>{{ $assignment->academicYear?->name ?? 'Unassigned year' }}</span>
                        <span><i class="ti ti-calendar" aria-hidden="true"></i>{{ $assignment->due_at ? 'Due '.$assignment->due_at->format('Y-m-d H:i').' '.config('app.timezone') : 'No due date' }}</span>
                        <span>{{ $assignment->max_points }} points</span>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-start align-items-sm-end gap-2">
                    <span class="small text-muted">{{ $assignment->creator?->name ?? 'Former teacher' }}</span>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('classes.coursework.assignments.show', [$schoolClass, $assignment]) }}">View assignment</a>
                </div>
            </div>
        </div></article>
    @empty
        <x-class-empty :title="$isTeacher ? 'No coursework assignments yet.' : 'No coursework has been published for this class.'" description="Assignments and their due dates will appear here." icon="ti-book-2" />
    @endforelse
    {{ $assignments->links() }}
</div>
@endsection
