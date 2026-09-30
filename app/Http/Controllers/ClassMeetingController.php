<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meetings\CancelClassMeetingSeriesRequest;
use App\Http\Requests\Meetings\StoreClassMeetingRequest;
use App\Http\Requests\Meetings\UpdateClassMeetingRequest;
use App\Models\ClassAttendanceRegister;
use App\Models\ClassMeeting;
use App\Models\ClassMeetingSeries;
use App\Models\CourseworkAssignment;
use App\Models\SchoolClass;
use App\Services\ClassMeetingSeriesService;
use App\Services\ClassMeetingService;
use App\Services\LiveKitMeetingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class ClassMeetingController extends Controller
{
    public function index(SchoolClass $schoolClass, LiveKitMeetingService $liveKit): Response
    {
        Gate::authorize('viewAny', [ClassMeeting::class, $schoolClass]);
        $schoolClass->loadMissing('academicYear');
        $meetings = $schoolClass->meetings()->with(['creator', 'series'])
            ->orderByRaw('CASE WHEN status = ? AND starts_at <= ? AND ends_at > ? THEN 0 WHEN status = ? AND ends_at > ? THEN 1 ELSE 2 END', [
                'scheduled', now()->addMinutes(config('livekit.join_before_minutes')), now()->subMinutes(config('livekit.join_after_minutes')),
                'scheduled', now(),
            ])
            ->orderBy('starts_at')->paginate(20);
        $joinableMeetingIds = $meetings->getCollection()
            ->filter(function (ClassMeeting $meeting) use ($schoolClass, $liveKit): bool {
                $meeting->setRelation('schoolClass', $schoolClass);

                return $liveKit->joinable($meeting);
            })->pluck('id')->all();

        $meetingCards = $meetings->getCollection()->map(function (ClassMeeting $meeting) use ($schoolClass, $joinableMeetingIds): array {
            $joinable = in_array($meeting->id, $joinableMeetingIds, true);
            $group = $joinable ? 'ready' : ($meeting->status === 'scheduled' && $meeting->ends_at->isFuture() ? 'upcoming' : 'earlier');

            return [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'status' => $meeting->status,
                'rescheduled' => $meeting->rescheduled_at !== null,
                'day' => $meeting->starts_at->format('j'),
                'month' => $meeting->starts_at->format('M'),
                'time' => $meeting->starts_at->format('D, M j, Y · g:i A').'–'.($meeting->ends_at->isSameDay($meeting->starts_at) ? $meeting->ends_at->format('g:i A') : $meeting->ends_at->format('D, M j, Y g:i A')).' '.config('app.timezone'),
                'host' => $meeting->creator?->name ?? 'Class teacher',
                'repeat' => $meeting->series
                    ? 'Ongoing '.str_replace('_', ' ', $meeting->series->recurrence).' · Meeting '.$meeting->occurrence_number
                    : ($meeting->occurrence_count > 1 ? ucfirst($meeting->recurrence).' · Meeting '.$meeting->occurrence_number.' of '.$meeting->occurrence_count : null),
                'group' => $group,
                'detailsUrl' => route('classes.meetings.show', [$schoolClass, $meeting]),
                'roomUrl' => $joinable ? route('classes.meetings.room', [$schoolClass, $meeting]) : null,
            ];
        })->values()->all();

        $sections = collect([
            ['label' => 'Overview', 'url' => route('classes.show', $schoolClass), 'allowed' => Gate::allows('view', $schoolClass)],
            ['label' => 'Assessments', 'url' => route('classes.assessments.index', $schoolClass), 'allowed' => in_array(auth()->user()->role->name, ['teacher', 'student'], true) && Gate::allows('view', $schoolClass)],
            ['label' => 'Coursework', 'url' => route('classes.coursework.index', $schoolClass), 'allowed' => Gate::allows('viewAny', [CourseworkAssignment::class, $schoolClass])],
            ['label' => 'Meetings', 'url' => route('classes.meetings.index', $schoolClass), 'allowed' => true],
            ['label' => 'Attendance', 'url' => route('classes.attendance.index', $schoolClass), 'allowed' => Gate::allows('viewAny', [ClassAttendanceRegister::class, $schoolClass])],
            ['label' => 'Channels', 'url' => route('classes.show', $schoolClass).'#class-channels', 'allowed' => Gate::allows('viewContent', $schoolClass)],
        ])->filter(fn (array $section): bool => $section['allowed'])->map(fn (array $section): array => ['label' => $section['label'], 'url' => $section['url']])->values()->all();

        return Inertia::render('Meetings/Index', [
            'title' => 'Meetings · '.$schoolClass->name,
            'schoolClass' => [
                'name' => $schoolClass->name,
                'archived' => $schoolClass->isArchived(),
                'academicYear' => $schoolClass->academicYear?->name,
                'overviewUrl' => Gate::allows('view', $schoolClass) ? route('classes.show', $schoolClass) : null,
            ],
            'classesUrl' => route('classes.index'),
            'sections' => $sections,
            'scheduleUrl' => Gate::allows('create', [ClassMeeting::class, $schoolClass]) ? route('classes.meetings.create', $schoolClass) : null,
            'meetings' => $meetingCards,
            'pagination' => [
                'page' => $meetings->currentPage(),
                'lastPage' => $meetings->lastPage(),
                'previousUrl' => $meetings->previousPageUrl(),
                'nextUrl' => $meetings->nextPageUrl(),
            ],
        ]);
    }

    public function create(SchoolClass $schoolClass): View
    {
        Gate::authorize('create', [ClassMeeting::class, $schoolClass]);

        return view('meetings.form', compact('schoolClass'));
    }

    public function store(StoreClassMeetingRequest $request, SchoolClass $schoolClass, ClassMeetingService $meetings, ClassMeetingSeriesService $series): RedirectResponse
    {
        $data = $request->validated();
        $meeting = $request->boolean('ongoing')
            ? $series->create($request->user(), $schoolClass, $data)
            : $meetings->create($request->user(), $schoolClass, $data);

        return redirect()->route('classes.meetings.show', [$schoolClass, $meeting])->with('success', 'Meeting schedule created.');
    }

    public function show(SchoolClass $schoolClass, ClassMeeting $meeting, LiveKitMeetingService $liveKit): View
    {
        $this->assertClass($schoolClass, $meeting);
        Gate::authorize('view', $meeting);
        $meeting->load(['creator', 'rescheduler', 'canceller', 'ender', 'series']);
        $joinAvailable = $liveKit->joinable($meeting);
        $videoConfigured = $liveKit->configured();

        return view('meetings.show', compact('schoolClass', 'meeting', 'joinAvailable', 'videoConfigured'));
    }

    public function update(UpdateClassMeetingRequest $request, SchoolClass $schoolClass, ClassMeeting $meeting, ClassMeetingService $meetings): RedirectResponse
    {
        $this->assertClass($schoolClass, $meeting);
        $meetings->update($request->user(), $schoolClass, $meeting, $request->validated());

        return redirect()->route('classes.meetings.show', [$schoolClass, $meeting])->with('success', 'Meeting updated.');
    }

    public function cancel(Request $request, SchoolClass $schoolClass, ClassMeeting $meeting, ClassMeetingService $meetings): RedirectResponse
    {
        $this->assertClass($schoolClass, $meeting);
        $meetings->cancel($request->user(), $schoolClass, $meeting);

        return redirect()->route('classes.meetings.show', [$schoolClass, $meeting])->with('success', 'Meeting cancelled.');
    }

    public function cancelSeries(CancelClassMeetingSeriesRequest $request, SchoolClass $schoolClass, ClassMeetingSeries $meetingSeries, ClassMeetingSeriesService $series): RedirectResponse
    {
        $series->cancel($request->user(), $schoolClass, $meetingSeries);

        return redirect()->route('classes.meetings.index', $schoolClass)->with('success', 'Future meetings in this series cancelled.');
    }

    private function assertClass(SchoolClass $schoolClass, ClassMeeting $meeting): void
    {
        abort_unless((int) $meeting->school_class_id === (int) $schoolClass->id, 404);
    }
}
