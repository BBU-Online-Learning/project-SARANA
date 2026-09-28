@extends('layouts.app')
@section('title', 'Edit academic context · '.$schoolClass->name)
@section('content')
<div class="page-container my-3">
    <nav aria-label="Breadcrumb" class="mb-3">
        <a href="{{ route('classes.index') }}">Classes</a> / <a href="{{ route('academics.index') }}">Academic structure</a> / {{ $schoolClass->name }}
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Academic context · {{ $schoolClass->name }}</h1>
            <p class="text-muted mb-0">Class #{{ $schoolClass->id }} · Join code {{ $schoolClass->join_code }} @if ($schoolClass->isArchived()) · Archived @endif</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('classes.show', $schoolClass) }}">Open class</a>
    </div>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul></div>
    @endif
    @php
        $selectedSubjectIds = collect(session()->hasOldInput() ? old('subject_ids', []) : $schoolClass->subjects->pluck('id')->all())
            ->map(fn ($id): string => (string) $id)->all();
    @endphp
    <form method="POST" action="{{ route('academics.classes.update', $schoolClass) }}" class="card">
        @csrf @method('PATCH')
        <div class="card-body">
            <p class="text-muted">Changing the year updates dated assignment history. Class ID, join code and current membership stay the same.</p>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="academic-class-year" class="form-label">Academic year</label>
                    <select id="academic-class-year" name="academic_year_id" class="form-select" required>
                        <option value="">Choose a year</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" @selected((string) old('academic_year_id', $schoolClass->academic_year_id) === (string) $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                    @error('academic_year_id') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-md-6">
                    <label for="academic-class-grade" class="form-label">Grade level</label>
                    <select id="academic-class-grade" name="grade_level_id" class="form-select">
                        <option value="">Unassigned</option>
                        @foreach ($grades as $grade)
                            <option value="{{ $grade->id }}" @selected((string) old('grade_level_id', $schoolClass->grade_level_id) === (string) $grade->id)>{{ $grade->name }}</option>
                        @endforeach
                    </select>
                    @error('grade_level_id') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <fieldset>
                <legend class="h5">Subjects</legend>
                <p class="text-muted small">Select the subjects taught in this class. Leave all unchecked to clear them.</p>
                <div class="row g-2 mb-3">
                    @forelse ($subjects as $subject)
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-check">
                                <input id="academic-subject-{{ $subject->id }}" class="form-check-input" type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" @checked(in_array((string) $subject->id, $selectedSubjectIds, true))>
                                <label for="academic-subject-{{ $subject->id }}" class="form-check-label">{{ $subject->code }} · {{ $subject->name }}</label>
                            </div>
                        </div>
                    @empty
                        <p>No subjects defined yet. <a href="{{ route('academics.index') }}">Add a subject</a>.</p>
                    @endforelse
                </div>
            </fieldset>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit" @disabled($years->isEmpty())>Save academic context</button>
                <a class="btn btn-outline-secondary" href="{{ route('academics.index') }}">Back to class list</a>
            </div>
        </div>
    </form>
</div>
@endsection
