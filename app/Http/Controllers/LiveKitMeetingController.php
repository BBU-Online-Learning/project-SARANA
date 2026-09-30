<?php

namespace App\Http\Controllers;

use App\Models\ClassMeeting;
use App\Models\SchoolClass;
use App\Services\LiveKitMeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LiveKitMeetingController extends Controller
{
    public function show(SchoolClass $schoolClass, ClassMeeting $meeting, LiveKitMeetingService $liveKit): View
    {
        $this->authorizeJoin($schoolClass, $meeting, $liveKit);

        $removableUserIds = Gate::allows('manageJoinRequests', $meeting)
            ? $schoolClass->memberRecords()->where('role', 'student')->pluck('user_id')->all()
            : [];

        return view('meetings.room', compact('schoolClass', 'meeting', 'removableUserIds'));
    }

    public function credentials(Request $request, SchoolClass $schoolClass, ClassMeeting $meeting, LiveKitMeetingService $liveKit): JsonResponse
    {
        $this->authorizeJoin($schoolClass, $meeting, $liveKit);
        Gate::authorize('issueToken', $meeting);

        return response()->json($liveKit->credentials($request->user(), $meeting))
            ->header('Cache-Control', 'no-store, private');
    }

    private function authorizeJoin(SchoolClass $schoolClass, ClassMeeting $meeting, LiveKitMeetingService $liveKit): void
    {
        abort_unless((int) $meeting->school_class_id === (int) $schoolClass->id, 404);
        Gate::authorize('view', $meeting);
        abort_unless($liveKit->configured(), 503, 'Class meeting video is not configured.');
        abort_unless($liveKit->joinable($meeting), 403, 'This meeting is not open for joining.');
    }
}
