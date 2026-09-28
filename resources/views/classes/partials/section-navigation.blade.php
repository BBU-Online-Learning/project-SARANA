<nav class="learning-class-nav class-section-nav" aria-label="Class sections">
    @can('view', $schoolClass)
        <a href="{{ route('classes.show', $schoolClass) }}" @if ($activeSection === 'overview') class="is-active" aria-current="page" @endif>Overview</a>
    @endcan
    @if (in_array(auth()->user()->role->name, ['teacher', 'student'], true) && auth()->user()->can('view', $schoolClass))
        <a href="{{ route('classes.assessments.index', $schoolClass) }}">Assessments</a>
    @endif
    @can('viewAny', [\App\Models\CourseworkAssignment::class, $schoolClass])
        <a href="{{ route('classes.coursework.index', $schoolClass) }}" @if ($activeSection === 'coursework') class="is-active" aria-current="page" @endif>Coursework</a>
    @endcan
    @can('viewAny', [\App\Models\ClassMeeting::class, $schoolClass])
        <a href="{{ route('classes.meetings.index', $schoolClass) }}" @if ($activeSection === 'meetings') class="is-active" aria-current="page" @endif>Meetings</a>
    @endcan
    @can('viewAny', [\App\Models\ClassAttendanceRegister::class, $schoolClass])
        <a href="{{ route('classes.attendance.index', $schoolClass) }}" @if ($activeSection === 'attendance') class="is-active" aria-current="page" @endif>Attendance</a>
    @endcan
    @can('viewContent', $schoolClass)
        <a href="{{ route('classes.show', $schoolClass) }}#class-channels">Channels</a>
    @endcan
    @if ($includeHomeAnchors ?? false)
        <a href="#class-members">Members</a>
        @canany(['manageLifecycle', 'manageMembers', 'manageChannels'], $schoolClass)
            <a href="#class-actions">Class actions</a>
        @endcanany
        <a href="{{ route('chat.index') }}">Chats</a>
    @endif
</nav>
