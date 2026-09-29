@extends('layouts.app')
@section('title', 'Academic structure')
@section('content')
<div class="page-container">
    <a href="{{ route('classes.index') }}" class="btn btn-link mb-3">Back to classes</a>
    <h1 class="h3 mb-2">Academic structure</h1>
    <p class="text-muted">Set up years, grade levels and subjects, then assign them to existing classes. Current membership and quiz access stay with each class.</p>
    <a class="btn btn-outline-primary mb-3" href="{{ route('academics.reporting-periods.index') }}">Manage reporting periods</a>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul></div>
    @endif
    <section class="card mb-4" aria-labelledby="academic-classes-heading">
        <div class="card-body border-bottom">
            <h2 class="h4 mb-1" id="academic-classes-heading">Class context</h2>
            <p class="text-muted mb-3">Find a class, then edit its academic year, grade level and subjects. {{ $classes->total() }} matching {{ $classes->total() === 1 ? 'class' : 'classes' }}.</p>
            <form method="GET" action="{{ route('academics.index') }}" class="row g-2 align-items-end" role="search" aria-label="Find classes for academic management">
                <div class="col-lg-5">
                    <label for="academic-class-search" class="form-label">Class name or join code</label>
                    <input id="academic-class-search" name="search" type="search" class="form-control" value="{{ $search }}" maxlength="100">
                </div>
                <div class="col-lg-3">
                    <label for="academic-year-filter" class="form-label">Academic year</label>
                    <select id="academic-year-filter" name="academic_year_id" class="form-select">
                        <option value="">All years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" @selected((string) $academicYearId === (string) $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label for="academic-status-filter" class="form-label">Status</label>
                    <select id="academic-status-filter" name="status" class="form-select">
                        <option value="all" @selected($status === 'all')>All classes</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="archived" @selected($status === 'archived')>Archived</option>
                    </select>
                </div>
                <div class="col-lg-2 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    @if ($search !== '' || $academicYearId || $status !== 'all')
                        <a class="btn btn-outline-secondary" href="{{ route('academics.index') }}">Clear</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($classes as $schoolClass)
                <article class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 py-3">
                    <div>
                        <h3 class="h5 mb-1">{{ $schoolClass->name }} @if ($schoolClass->isArchived()) <span class="badge bg-secondary">Archived</span> @endif</h3>
                        <p class="text-muted small mb-1">Class #{{ $schoolClass->id }} · {{ $schoolClass->join_code }}</p>
                        <p class="mb-0">{{ $schoolClass->academicYear?->name ?? 'Year unassigned' }} · {{ $schoolClass->gradeLevel?->name ?? 'Grade unassigned' }} · {{ $schoolClass->subjects->pluck('name')->join(', ') ?: 'No subjects' }}</p>
                    </div>
                    <a class="btn btn-outline-primary flex-shrink-0" href="{{ route('academics.classes.edit', $schoolClass) }}">Edit academic context</a>
                </article>
            @empty
                <p class="card-body mb-0">No classes match these filters.</p>
            @endforelse
        </div>
        @if ($classes->hasPages())
            <div class="card-body border-top">{{ $classes->links() }}</div>
        @endif
    </section>
    <div class="row g-3 mb-4">
        <div class="col-lg-4"><div class="card"><div class="card-body">
            <h2 class="h5">Academic years</h2>
            <form method="POST" action="{{ route('academics.years.store') }}">@csrf
                <label for="year-name" class="form-label">Name</label><input id="year-name" name="name" class="form-control mb-2" value="{{ old('name') }}" required>
                <label for="year-start" class="form-label">Start date</label><input id="year-start" type="date" name="starts_on" class="form-control mb-2" value="{{ old('starts_on') }}" required>
                <label for="year-end" class="form-label">End date</label><input id="year-end" type="date" name="ends_on" class="form-control mb-2" value="{{ old('ends_on') }}" required>
                <label for="year-status" class="form-label">Status</label><select id="year-status" name="status" class="form-select mb-2"><option value="planned">Planned</option><option value="active">Active</option><option value="closed">Closed</option></select>
                <button class="btn btn-primary" type="submit">Add year</button>
            </form>
            <ul class="mt-3 mb-0">@foreach ($years as $year) <li>{{ $year->name }} ({{ $year->status }})</li> @endforeach</ul>
        </div></div></div>
        <div class="col-lg-4"><div class="card"><div class="card-body">
            <h2 class="h5">Grade levels</h2>
            <form method="POST" action="{{ route('academics.grades.store') }}">@csrf
                <label for="grade-name" class="form-label">Name</label><input id="grade-name" name="name" class="form-control mb-2" required>
                <label for="grade-sequence" class="form-label">Order</label><input id="grade-sequence" type="number" min="1" name="sequence" class="form-control mb-2" required>
                <button class="btn btn-primary" type="submit">Add grade</button>
            </form>
            <ul class="mt-3 mb-0">@foreach ($grades as $grade) <li>{{ $grade->name }}</li> @endforeach</ul>
        </div></div></div>
        <div class="col-lg-4"><div class="card"><div class="card-body">
            <h2 class="h5">Subjects</h2>
            <form method="POST" action="{{ route('academics.subjects.store') }}">@csrf
                <label for="subject-code" class="form-label">Code</label><input id="subject-code" name="code" class="form-control mb-2" required>
                <label for="subject-name" class="form-label">Name</label><input id="subject-name" name="name" class="form-control mb-2" required>
                <button class="btn btn-primary" type="submit">Add subject</button>
            </form>
            <ul class="mt-3 mb-0">@foreach ($subjects as $subject) <li>{{ $subject->code }} · {{ $subject->name }}</li> @endforeach</ul>
        </div></div></div>
    </div>
</div>
@endsection
