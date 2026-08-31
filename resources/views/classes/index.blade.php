@extends('layouts.app')
@section('bodyClass', 'class-page')

@section('content')
<div class="page-container">
    @if (session('error')) <div class="alert alert-danger" role="alert">{{ session('error') }}</div> @endif
    @if (session('success')) <div class="alert alert-success" role="status">{{ session('success') }}</div> @endif
    <div class="card class-hero mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge class-hero-badge">Class Dashboard</span>
                    </div>

                    <h4 class="class-title mb-2">Classes</h4>

                    <p class="class-description mb-0">
                        {{ auth()->user()->can('manage-classes') ? 'Create, join, and manage your class spaces from one place.' : 'Join your classes and open your class conversations.' }}
                    </p>
                </div>

                <div class="class-meta-pill">
                    <span>Available actions</span>
                    <strong>{{ auth()->user()->can('manage-classes') ? 'Create, Join, Open' : 'Join, Open' }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            @can('manage-classes')
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-3">Create Class</h5>

                        <form method="POST" action="{{ route('classes.store') }}" enctype="multipart/form-data">
                            @csrf

                            @if (app(\App\Services\ClassAccessService::class)->administrator(auth()->user()))
                                <div class="mb-3">
                                    <label class="form-label">Teacher Owner</label>
                                    <select name="owner_id" class="form-select" required>
                                        <option value="">Select an eligible Teacher</option>
                                        @foreach ($eligibleTeachers as $teacher)
                                            <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Creating a class does not enroll you for message access.</small>
                                    @error('owner_id') <div class="text-danger">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Class Name</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Class Image</label>
                                <input type="file" name="avatar" class="form-control">
                                @error('avatar')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Create Class</button>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Join Class</h5>

                    <form method="POST" action="{{ route('classes.join') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Join Code</label>
                            <input type="text" name="join_code" class="form-control" value="{{ old('join_code') }}" required>
                            @error('join_code')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-success w-100">Join Class</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">{{ app(\App\Services\ClassAccessService::class)->administrator(auth()->user()) ? 'Institution Classes' : 'My Classes' }}</h5>
                            <p class="text-muted mb-0 small">Open a class to view its channels and members.</p>
                        </div>

                        <span class="badge bg-info-subtle text-info">
                            {{ $classes->count() }} total
                        </span>
                    </div>

                    @forelse ($classes as $schoolClass)
                        <div class="class-list-card mb-3">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <span class="badge class-hero-badge">Class</span>
                                        @if ($schoolClass->isArchived())
                                            <span class="badge bg-warning text-dark">Archived</span>
                                        @endif
                                        <span class="badge bg-dark">{{ $schoolClass->channels->count() }} channels</span>
                                    </div>

                                    <h6 class="mb-1">
                                        <a href="{{ route('classes.show', $schoolClass) }}" class="text-decoration-none">
                                            {{ $schoolClass->name }}
                                        </a>
                                    </h6>

                                    <div class="text-muted small">
                                        {{ $schoolClass->description ?: 'No description yet.' }}
                                    </div>
                                </div>

                                <div class="d-flex flex-column align-items-stretch gap-2">
                                    <div class="class-meta-pill class-meta-pill-sm">
                                        <span>Members</span>
                                        <strong>{{ $schoolClass->members_count }}</strong>
                                    </div>

                                    <a href="{{ route('classes.show', $schoolClass) }}" class="btn btn-primary">
                                        Open Class
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-light mb-0">
                            You are not in any classes yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
