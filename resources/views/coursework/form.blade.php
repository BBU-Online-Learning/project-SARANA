@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', $assignment ? 'Edit coursework' : 'Create coursework')
@section('content')
<div class="page-container my-3">
    @php
        $breadcrumbs = [['label' => 'Coursework', 'url' => route('classes.coursework.index', $schoolClass)]];
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'coursework', 'title' => $assignment ? 'Edit assignment draft' : 'Create assignment draft', 'description' => 'Students will see this assignment after you publish it.', 'breadcrumbs' => $breadcrumbs])
    <div class="card"><div class="card-body">
        <p class="text-muted">Published assignments are kept stable for grading history.</p>
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ $assignment ? route('classes.coursework.assignments.update', [$schoolClass, $assignment]) : route('classes.coursework.assignments.store', $schoolClass) }}">
            @csrf
            @if ($assignment) @method('PATCH') @endif
            <div class="mb-3"><label class="form-label" for="coursework-title">Title</label><input class="form-control" id="coursework-title" name="title" required maxlength="180" value="{{ old('title', $assignment?->title) }}"></div>
            <div class="mb-3"><label class="form-label" for="coursework-instructions">Instructions</label><textarea class="form-control" id="coursework-instructions" name="instructions" rows="7" maxlength="20000">{{ old('instructions', $assignment?->instructions) }}</textarea></div>
            <div class="mb-3"><label class="form-label" for="coursework-subject">Subject (optional)</label><select id="coursework-subject" name="subject_id" class="form-select"><option value="">General coursework</option>@foreach ($schoolClass->subjects as $subject)<option value="{{ $subject->id }}" @selected((string) old('subject_id', $assignment?->subject_id) === (string) $subject->id)>{{ $subject->name }}</option>@endforeach</select></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="coursework-points">Maximum points</label><input class="form-control" id="coursework-points" type="number" min="0.01" max="999999.99" step="0.01" name="max_points" required value="{{ old('max_points', $assignment?->max_points ?? '100.00') }}"></div>
                <div class="col-md-6"><label class="form-label" for="coursework-due">Due date (optional, {{ config('app.timezone') }} time)</label><input class="form-control" id="coursework-due" type="datetime-local" name="due_at" value="{{ old('due_at', $assignment?->due_at?->format('Y-m-d\\TH:i')) }}"><small class="text-muted">Enter the date and time in the school timezone.</small></div>
            </div>
            <input type="hidden" name="allow_resubmissions" value="0">
            <div class="form-check mb-3"><input class="form-check-input" id="coursework-resubmit" type="checkbox" name="allow_resubmissions" value="1" @checked(old('allow_resubmissions', $assignment?->allow_resubmissions ?? true))><label class="form-check-label" for="coursework-resubmit">Allow students to resubmit while the assignment is open</label></div>
            <button class="btn btn-primary" type="submit">Save draft</button>
        </form>
    </div></div>
</div>
@endsection
