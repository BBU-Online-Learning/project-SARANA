@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', $assignment->title)
@section('content')
<div class="page-container my-3">
    @php
        $breadcrumbs = [['label' => 'Coursework', 'url' => route('classes.coursework.index', $schoolClass)]];
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'coursework', 'title' => $assignment->title, 'description' => $isTeacher ? 'Review assignment details and submitted work.' : 'Read the instructions, prepare your response, and review feedback.', 'breadcrumbs' => $breadcrumbs])
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div><h2 class="visually-hidden">Assignment details</h2><p class="text-muted">{{ $schoolClass->name }} · {{ $assignment->subject?->name ?? 'General' }} · {{ $assignment->academicYear?->name ?? 'Unassigned year' }} · {{ $assignment->max_points }} points · {{ $assignment->due_at ? 'Due '.$assignment->due_at->format('Y-m-d H:i').' '.config('app.timezone') : 'No due date' }}</p></div>
            <x-class-status :value="$assignment->status" />
        </div>
        <div style="white-space: pre-wrap">{{ $assignment->instructions ?: 'No additional instructions.' }}</div>
        @if ($isTeacher)
            <div class="d-flex flex-wrap gap-2 mt-3">
                @can('update', $assignment)
                    <a class="btn btn-outline-primary" href="{{ route('classes.coursework.assignments.edit', [$schoolClass, $assignment]) }}">Edit draft</a>
                    <form method="POST" action="{{ route('classes.coursework.assignments.publish', [$schoolClass, $assignment]) }}">@csrf<button class="btn btn-primary" type="submit">Publish</button></form>
                @endcan
                @can('close', $assignment)
                    <form method="POST" action="{{ route('classes.coursework.assignments.close', [$schoolClass, $assignment]) }}">@csrf<button class="btn btn-outline-secondary" type="submit">Close submissions</button></form>
                @endcan
            </div>
        @endif
    </div></div>

    @if ($isTeacher)
        <div class="card"><div class="card-body">
            <h2 class="h5">Submitted work</h2>
            @forelse ($submissions as $submission)
                <div class="d-flex flex-wrap justify-content-between gap-2 border-top py-2">
                    <div><strong>{{ $submission->student?->name ?? 'Former student' }}</strong><div class="small text-muted">Last submitted {{ $submission->last_submitted_at?->format('Y-m-d H:i') }}</div></div>
                    <a href="{{ route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]) }}">Review submission</a>
                </div>
            @empty
                <p class="text-muted mb-0">No work has been submitted yet. Student drafts stay private.</p>
            @endforelse
            {{ $submissions->links() }}
        </div></div>
    @else
        @php
            $draft = $ownSubmission?->revisions->firstWhere('draft_slot', 1);
            $isSubmitted = $ownSubmission?->status === 'submitted';
        @endphp
        @if ($assignment->status === 'published')
            <ol class="class-workflow" aria-label="Coursework submission steps">
                <li class="{{ $draft || $isSubmitted ? 'is-done' : 'is-current' }}"><strong>Step 1</strong>Save a private draft</li>
                <li class="{{ $isSubmitted ? 'is-done' : ($draft ? 'is-current' : '') }}"><strong>Step 2</strong>Review your work</li>
                <li class="{{ $isSubmitted ? 'is-done' : '' }}"><strong>Step 3</strong>Submit response</li>
            </ol>
        @endif
        @if ($isSubmitted)
            <div class="class-summary-panel mb-3" role="status">
                <strong>Submission recorded</strong>
                <p class="mb-0 small">Your teacher can review your submitted revision. You can start a new revision if resubmissions are available.</p>
            </div>
        @endif
        @if ($assignment->status === 'published' && (! $ownSubmission || $ownSubmission->status === 'draft'))
            <div class="card mb-3"><div class="card-body">
                <h2 class="h5">{{ $draft ? 'Your draft' : 'Start your response' }}</h2>
                <p class="text-muted">Write your response and save it as a private draft. Review your saved work and attachments, then select Submit response to send it to your teacher. Saving a draft alone does not submit it.</p>
                <form method="POST" action="{{ route('classes.coursework.drafts.save', [$schoolClass, $assignment]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3"><label class="form-label" for="coursework-body">Written response</label><textarea class="form-control" id="coursework-body" name="body" rows="8" maxlength="20000">{{ old('body', $draft?->body) }}</textarea></div>
                    <div class="mb-3"><label class="form-label" for="coursework-files">Attach files</label><input class="form-control" id="coursework-files" type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png"><small class="text-muted">Up to five files, 10 MB each and 25 MB total. Files are private.</small></div>
                    <button class="btn btn-outline-primary" type="submit">Save draft</button>
                </form>
                @if ($draft)
                    <p class="small text-muted mt-3 mb-1">Review your draft and attachments before submitting. You can still edit the draft above.</p>
                    @foreach ($draft->attachments as $attachment)
                        <div class="d-flex gap-2 align-items-center mt-2">
                            <a href="{{ route('classes.coursework.attachments.download', [$schoolClass, $assignment, $ownSubmission, $draft, $attachment]) }}">{{ $attachment->original_name }}</a>
                            <form method="POST" action="{{ route('classes.coursework.attachments.destroy', [$schoolClass, $assignment, $ownSubmission, $draft, $attachment]) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger" type="submit">Remove</button></form>
                        </div>
                    @endforeach
                    <form method="POST" action="{{ route('classes.coursework.submissions.submit', [$schoolClass, $assignment]) }}" class="mt-3">@csrf<button class="btn btn-primary" type="submit">Submit response</button></form>
                @endif
            </div></div>
        @elseif ($assignment->status === 'published' && $ownSubmission?->status === 'submitted' && $assignment->allow_resubmissions)
            <form method="POST" action="{{ route('classes.coursework.submissions.resubmit', [$schoolClass, $assignment]) }}" class="mb-3">@csrf<button class="btn btn-outline-primary" type="submit">Start resubmission</button></form>
        @endif

        @if ($ownSubmission)
            <div class="card mb-3"><div class="card-body">
                <h2 class="h5">Revision history</h2>
                @foreach ($ownSubmission->revisions->sortByDesc('revision_number') as $revision)
                    <div class="border-top py-3"><strong>Revision {{ $revision->revision_number }} · {{ ucfirst($revision->status) }}</strong>
                        @if ($revision->is_late) <span class="badge bg-warning text-dark">Late</span> @endif
                        <div class="small text-muted">{{ $revision->submitted_at?->format('Y-m-d H:i') }}</div>
                        @if ($revision->status === 'submitted')
                            <p style="white-space: pre-wrap">{{ $revision->body }}</p>
                            @foreach ($revision->attachments as $attachment)
                                <div><a href="{{ route('classes.coursework.attachments.download', [$schoolClass, $assignment, $ownSubmission, $revision, $attachment]) }}">{{ $attachment->original_name }}</a></div>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            </div></div>
            <div class="card"><div class="card-body"><h2 class="h5">Grade history</h2>
                @php
                    $latestSubmittedRevision = $ownSubmission->revisions->where('status', 'submitted')->sortByDesc('revision_number')->first();
                @endphp
                @if ($latestSubmittedRevision && $ownSubmission->grades->first()?->coursework_revision_id !== $latestSubmittedRevision->id)
                    <p class="text-muted">The latest submitted revision awaits grading.</p>
                @endif
                @if ($ownSubmission->grades->first())
                    <div class="class-summary-panel mb-3">
                        <span class="small text-muted">Latest grade</span>
                        <strong class="d-block fs-4">{{ $ownSubmission->grades->first()->points_awarded }}/{{ $ownSubmission->grades->first()->max_points_snapshot }}</strong>
                    </div>
                @endif
                @forelse ($ownSubmission->grades as $grade)
                    <div class="border-top py-2"><strong>{{ $grade->points_awarded }}/{{ $grade->max_points_snapshot }}</strong> for revision {{ $grade->revision?->revision_number }} · {{ $grade->created_at->format('Y-m-d H:i') }}
                        @if ($grade->feedback) <p class="mb-1" style="white-space: pre-wrap">{{ $grade->feedback }}</p> @endif
                        @if ($grade->change_reason) <small class="text-muted">Change reason: {{ $grade->change_reason }}</small> @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">No grade yet.</p>
                @endforelse
            </div></div>
        @endif
    @endif
</div>
@endsection
