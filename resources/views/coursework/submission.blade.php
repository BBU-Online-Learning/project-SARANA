@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Coursework submission')
@section('content')
<div class="page-container my-3">
    @php
        $pageTitle = ($submission->student?->name ?? 'Former student').' · '.$assignment->title;
        $breadcrumbs = [
            ['label' => 'Coursework', 'url' => route('classes.coursework.index', $schoolClass)],
            ['label' => $assignment->title, 'url' => route('classes.coursework.assignments.show', [$schoolClass, $assignment])],
        ];
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'coursework', 'title' => $pageTitle, 'description' => 'Review revisions, feedback, and grading history.', 'breadcrumbs' => $breadcrumbs])
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @foreach ($revisions as $revision)
        <section class="card class-list-card mb-3" aria-labelledby="revision-{{ $revision->id }}"><div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h2 id="revision-{{ $revision->id }}" class="h5 mb-0">Revision {{ $revision->revision_number }}</h2>
                <x-class-status :value="$revision->status" />
                @if ($revision->is_late)<x-class-status value="late" />@endif
            </div>
            <p class="class-list-meta mb-2">{{ $revision->submitted_at?->format('Y-m-d H:i') ?? 'Draft' }}</p>
            <p class="mb-2" style="white-space: pre-wrap">{{ $revision->body }}</p>
            @if ($revision->attachments->isNotEmpty())
                <h3 class="h6">Attachments</h3>
                <ul class="mb-0">
                    @foreach ($revision->attachments as $attachment)
                        <li><a href="{{ route('classes.coursework.attachments.download', [$schoolClass, $assignment, $submission, $revision, $attachment]) }}">Download {{ $attachment->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div></section>
    @endforeach
    <section class="card mb-3" aria-labelledby="grade-history-heading"><div class="card-body"><h2 id="grade-history-heading" class="h5">Grade history</h2>
        @if ($revisions->first() && $submission->grades->first()?->coursework_revision_id !== $revisions->first()->id)
            <p class="text-muted">The latest submitted revision awaits grading.</p>
        @endif
        @forelse ($submission->grades as $grade)
            <div class="border-top py-3"><div class="d-flex flex-wrap align-items-center gap-2"><strong>{{ $grade->points_awarded }}/{{ $grade->max_points_snapshot }}</strong><span class="class-list-meta">Revision {{ $grade->revision?->revision_number }} · {{ $grade->created_at->format('Y-m-d H:i') }}</span></div>
                @if ($grade->feedback)<p class="mb-1" style="white-space: pre-wrap">{{ $grade->feedback }}</p>@endif
                @if ($grade->change_reason)<small class="text-muted">Change reason: {{ $grade->change_reason }}</small>@endif
            </div>
        @empty
            <p class="text-muted mb-0">No grades recorded.</p>
        @endforelse
    </div></div>
    @can('grade', $submission)
        <div class="card"><div class="card-body"><h2 class="h5">{{ $submission->grades->isEmpty() ? 'Grade submission' : 'Record grade change' }}</h2>
            <form method="POST" action="{{ route('classes.coursework.submissions.grade', [$schoolClass, $assignment, $submission]) }}">@csrf
                <div class="mb-3"><label for="grade-points" class="form-label">Points (maximum {{ $assignment->max_points }})</label><input id="grade-points" name="points_awarded" type="number" min="0" max="{{ $assignment->max_points }}" step="0.01" value="{{ old('points_awarded', $submission->grades->first()?->points_awarded) }}" class="form-control" required></div>
                <div class="mb-3"><label for="grade-feedback" class="form-label">Feedback</label><textarea id="grade-feedback" name="feedback" rows="5" maxlength="5000" class="form-control">{{ old('feedback') }}</textarea></div>
                @if ($submission->grades->isNotEmpty())<div class="mb-3"><label for="grade-reason" class="form-label">Reason for changing the grade</label><input id="grade-reason" name="change_reason" class="form-control" maxlength="500" required value="{{ old('change_reason') }}"></div>@endif
                <button class="btn btn-primary" type="submit">Record grade</button>
            </form>
        </div></div>
    @endcan
</div>
@endsection
