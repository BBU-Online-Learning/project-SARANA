<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meetings\CancelClassMeetingSeriesRequest;
use App\Http\Requests\Meetings\StoreClassMeetingRequest;
use App\Http\Requests\Meetings\UpdateClassMeetingRequest;
use App\Models\ClassMeeting;
use App\Models\ClassMeetingSeries;
use App\Models\SchoolClass;
use App\Services\ClassMeetingSeriesService;
use App\Services\ClassMeetingService;
use App\Services\LiveKitMeetingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClassMeetingController extends Controller
{
    public function index(SchoolClass $schoolClass): View
    {
        Gate::authorize('viewAny', [ClassMeeting::class, $schoolClass]);
        $meetings = $schoolClass->meetings()->with(['creator', 'series'])
            ->orderByRaw('CASE WHEN starts_at < ? THEN 1 ELSE 0 END', [now()])
            ->orderBy('starts_at')->paginate(20);

        return view('meetings.index', compact('schoolClass', 'meetings'));
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
        $meeting->load(['creator', 'rescheduler', 'canceller', 'series']);
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
