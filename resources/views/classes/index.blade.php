@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', 'Classes')
@php
    $classAdministration = auth()->user()->can('access-admin');
    $classHeading = $classAdministration ? 'Class administration' : 'My classes';
    $classIntroduction = $classAdministration
        ? 'Organize institution classes, assign teacher owners and manage membership.'
        : (auth()->user()->can('manage-classes')
            ? 'Prepare your class spaces, connect with students and open your teaching conversations.'
            : 'Join a class with your teacher’s code, then open its announcements and discussions.');
@endphp

@section('content')
@if(in_array(auth()->user()->role->name, ['teacher', 'student'], true))
    @include('classes.partials.learning-index')
@else
<div class="page-container">
    <div class="card class-hero mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge class-hero-badge">{{ $classAdministration ? 'School management' : 'Your classroom' }}</span>
                    </div>

                    <h1 class="class-title h3 mb-2">{{ $classHeading }}</h1>

                    <p class="class-description mb-0">
                        {{ $classIntroduction }}
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
            @include('classes.partials.enrollment-forms')
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">{{ app(\App\Services\ClassAccessService::class)->administrator(auth()->user()) ? 'Institution Classes' : 'My Classes' }}</h5>
                            <p class="text-muted mb-0 small">{{ $classAdministration ? 'Open a class to manage its information and membership.' : 'Choose a class to open its announcements, discussions and members.' }}</p>
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
                                        {{ $classAdministration ? 'Manage Class' : 'Open Class' }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-light mb-0">
                            {{ $classAdministration ? 'No institution classes yet. Create a class and assign a teacher owner to get started.' : 'You are not in any classes yet. Join with a code to get started.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
