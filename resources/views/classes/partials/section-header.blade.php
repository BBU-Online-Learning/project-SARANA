<nav aria-label="Breadcrumb" class="class-section-breadcrumb">
    <ol class="breadcrumb mb-3">
        <li class="breadcrumb-item"><a href="{{ route('classes.index') }}">Classes</a></li>
        <li class="breadcrumb-item">
            @can('view', $schoolClass)
                <a href="{{ route('classes.show', $schoolClass) }}">{{ $schoolClass->name }}</a>
            @else
                {{ $schoolClass->name }}
            @endcan
        </li>
        @foreach ($breadcrumbs ?? [] as $breadcrumb)
            <li class="breadcrumb-item"><a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a></li>
        @endforeach
        <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
    </ol>
</nav>

<header class="card class-hero class-section-hero mb-3">
    <div class="card-body d-flex flex-column flex-lg-row align-items-start justify-content-between gap-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="badge class-hero-badge">{{ ucfirst($activeSection) }}</span>
                @if ($schoolClass->isArchived()) <span class="badge bg-secondary">Archived class</span> @endif
            </div>
            <h1 class="class-title h3 mb-2">{{ $title }}</h1>
            <p class="class-description mb-0">{{ $description }}</p>
            <p class="small text-muted mt-2 mb-0">{{ $schoolClass->name }}@if ($schoolClass->academicYear) · {{ $schoolClass->academicYear->name }} @endif</p>
        </div>
        @if ($showSectionActions ?? false)
            <div class="d-flex flex-wrap gap-2 class-section-actions">
                @if ($activeSection === 'coursework')
                    @can('create', [\App\Models\CourseworkAssignment::class, $schoolClass])
                        <a class="btn btn-primary" href="{{ route('classes.coursework.assignments.create', $schoolClass) }}">Create assignment</a>
                    @endcan
                @elseif ($activeSection === 'meetings')
                    @can('create', [\App\Models\ClassMeeting::class, $schoolClass])
                        <a class="btn btn-primary" href="{{ route('classes.meetings.create', $schoolClass) }}">Schedule meeting</a>
                    @endcan
                @elseif ($activeSection === 'attendance')
                    @can('report', [\App\Models\ClassAttendanceRegister::class, $schoolClass])
                        <a class="btn btn-outline-primary" href="{{ route('classes.attendance.report', $schoolClass) }}">Reports and export</a>
                    @endcan
                    @can('create', [\App\Models\ClassAttendanceRegister::class, $schoolClass])
                        <a class="btn btn-primary" href="#attendance-open-form">Open dated roster</a>
                    @endcan
                    @if (auth()->user()->role->name === 'student')
                        <a class="btn btn-outline-primary" href="{{ route('attendance.mine') }}">My attendance</a>
                    @endif
                @endif
            </div>
        @endif
    </div>
</header>

@include('classes.partials.section-navigation')
