@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Attendance · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    @php
        $pageTitle = $register->attendance_date->format('d M Y');
        $breadcrumbs = [['label' => 'Attendance', 'url' => route('classes.attendance.index', $schoolClass)]];
        $rosterTotal = $records->count();
        $markedCount = $records->whereNotNull('status')->count();
    @endphp
    @include('classes.partials.section-header', ['activeSection' => 'attendance', 'title' => $pageTitle, 'description' => 'Dated attendance roster for this class.', 'breadcrumbs' => $breadcrumbs])
    <p>Roster captured {{ $register->roster_snapshot_at->format('d M Y H:i') }}. {{ $register->finalized_at ? 'Finalized' : ($register->reviewed_at ? 'Reviewed and ready to finalize' : 'Open for entry') }}.</p>
    @if ($isTeacher)
        <section class="class-summary-panel mb-3" aria-label="Attendance progress">
            <div class="attendance-progress">
                <div><strong>{{ $markedCount }} of {{ $rosterTotal }} students marked</strong><div class="small text-muted">{{ $rosterTotal - $markedCount }} still unmarked</div></div>
                <progress id="attendance-marked-progress" value="{{ $markedCount }}" max="{{ max($rosterTotal, 1) }}" aria-label="Students marked"></progress>
            </div>
            <dl class="attendance-counts">
                @foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $status => $label)
                    <div><dt>{{ $label }}</dt><dd>{{ $records->where('status', $status)->count() }}</dd></div>
                @endforeach
            </dl>
            <x-class-status :value="$register->finalized_at ? 'finalized' : ($register->reviewed_at ? 'reviewed' : 'open')" />
        </section>
    @else
        <div class="class-summary-panel mb-3"><strong>Your attendance</strong><div class="mt-2"><x-class-status :value="$records->first()?->status ?? 'unmarked'" /></div></div>
    @endif
    @if ($isTeacher && ! $register->finalized_at)
        <ol class="class-workflow" aria-label="Attendance steps">
            <li class="{{ $markedCount === $rosterTotal && $rosterTotal > 0 ? 'is-done' : 'is-current' }}"><strong>Step 1</strong>Save roster entries</li>
            <li class="{{ $register->reviewed_at ? 'is-done' : ($markedCount === $rosterTotal && $rosterTotal > 0 ? 'is-current' : '') }}"><strong>Step 2</strong>Confirm review</li>
            <li class="{{ $register->reviewed_at ? 'is-current' : '' }}"><strong>Step 3</strong>Finalize register</li>
        </ol>
    @endif
    @if (session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    @if ($records->isEmpty()) <div class="alert alert-info">This dated roster is empty. It cannot be finalized.</div> @endif

    @if ($isTeacher && ! $register->finalized_at && ! $schoolClass->isArchived())
        <form method="POST" action="{{ route('classes.attendance.bulk', [$schoolClass, $register]) }}">
            @csrf @method('PATCH')
            <p class="text-muted">You can save a partly completed roster. Leave a student unmarked until you know their status; every student must be marked before review.</p>
            <div class="table-responsive"><table class="table attendance-roster"><thead><tr><th scope="col">Student</th><th scope="col">Status</th><th scope="col">Note</th></tr></thead><tbody>
                @foreach ($records as $record)
                    @php($selectedStatus = old('entries.'.$record->id.'.status', $record->status))
                    <tr><td data-label="Student">{{ $record->student_name_snapshot }}</td><td data-label="Status">
                        <select class="form-select" name="entries[{{ $record->id }}][status]" aria-label="Status for {{ $record->student_name_snapshot }}">
                            <option value="" @selected($selectedStatus === null || $selectedStatus === '')>Unmarked</option>
                            @foreach (['present', 'absent', 'late', 'excused'] as $status)
                                <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </td><td data-label="Note"><input class="form-control" name="entries[{{ $record->id }}][note]" value="{{ old('entries.'.$record->id.'.note', $record->note) }}" maxlength="2000" aria-label="Note for {{ $record->student_name_snapshot }}"></td></tr>
                @endforeach
            </tbody></table></div>
            @if ($records->isNotEmpty()) <button class="btn btn-primary" type="submit">Save roster entries</button> @endif
        </form>
        <div class="attendance-actions d-flex flex-wrap gap-3 mt-3">
            <form method="POST" action="{{ route('classes.attendance.review', [$schoolClass, $register]) }}">
                @csrf
                <label class="d-block mb-2"><input type="checkbox" name="confirm_review" value="1" required> I reviewed every student and status</label>
                <button class="btn btn-outline-primary" type="submit" @disabled($rosterTotal === 0 || $markedCount !== $rosterTotal)>Confirm review</button>
                @if ($markedCount !== $rosterTotal) <p class="small text-muted mb-0 mt-2">Save a status for every student before review.</p> @endif
            </form>
            @if ($register->reviewed_at)
                <form method="POST" action="{{ route('classes.attendance.finalize', [$schoolClass, $register]) }}">
                    @csrf
                    <button class="btn btn-danger" type="submit">Finalize register</button>
                </form>
            @endif
        </div>
    @else
        <div class="table-responsive"><table class="table attendance-roster"><thead><tr><th scope="col">Student</th><th scope="col">Status</th><th scope="col">Note</th></tr></thead><tbody>
            @foreach ($records as $record)
                <tr><td data-label="Student">{{ $record->student_name_snapshot }}</td><td data-label="Status"><x-class-status :value="$record->status ?? 'unmarked'" /></td><td data-label="Note">{{ $record->note }}</td></tr>
            @endforeach
        </tbody></table></div>
    @endif

    @if ($register->finalized_at)
        @foreach ($records as $record)
            @if ($isTeacher && ! $schoolClass->isArchived())
                <details class="card card-body mb-2"><summary>Correct {{ $record->student_name_snapshot }} ({{ $record->status }})</summary>
                    <form method="POST" action="{{ route('classes.attendance.correct', [$schoolClass, $register, $record]) }}" class="mt-2">
                        @csrf
                        <label class="form-label" for="correction-status-{{ $record->id }}">Status</label><select id="correction-status-{{ $record->id }}" class="form-select" name="status" required>
                            @foreach (['present', 'absent', 'late', 'excused'] as $status)
                                <option value="{{ $status }}" @selected($record->status === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <label class="form-label mt-2" for="correction-note-{{ $record->id }}">Note</label><input id="correction-note-{{ $record->id }}" class="form-control" name="note" value="{{ $record->note }}" maxlength="2000">
                        <label class="form-label mt-2" for="correction-reason-{{ $record->id }}">Reason for correction</label><textarea id="correction-reason-{{ $record->id }}" class="form-control" name="reason" minlength="5" maxlength="2000" required></textarea>
                        <button class="btn btn-outline-primary mt-2" type="submit">Record correction</button>
                    </form>
                </details>
            @endif
            @if ($record->corrections->isNotEmpty())
                <h2 class="h6 mt-3">Correction history · {{ $record->student_name_snapshot }}</h2>
                <ul>@foreach ($record->corrections as $correction)
                    <li>{{ $correction->corrected_at->format('d M Y H:i') }} · {{ $correction->previous_status }} → {{ $correction->new_status }} · {{ $correction->reason }} @if ($isTeacher) ({{ $correction->corrector?->name ?? 'Former teacher' }}) @endif</li>
                @endforeach</ul>
            @endif
        @endforeach
    @endif
</div>
@endsection
