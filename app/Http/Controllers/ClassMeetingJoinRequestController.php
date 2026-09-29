<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meetings\DecideClassMeetingJoinRequestRequest;
use App\Models\ClassMeeting;
use App\Models\ClassMeetingJoinRequest;
use App\Models\SchoolClass;
use App\Services\ClassMeetingJoinService;
use App\Services\LiveKitMeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClassMeetingJoinRequestController extends Controller
{
    public function show(Request $request, SchoolClass $schoolClass, ClassMeeting $meeting, LiveKitMeetingService $liveKit): JsonResponse
    {
        $this->ensureScope($schoolClass, $meeting);
        Gate::authorize('view', $meeting);
        $joinRequest = $meeting->joinRequests()->where('requester_user_id', $request->user()->id)->first();

        return response()->json([
            'request' => $this->payload($joinRequest),
            'meeting_open' => $liveKit->joinable($meeting),
            'can_manage' => Gate::allows('manageJoinRequests', $meeting),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, SchoolClass $schoolClass, ClassMeeting $meeting, ClassMeetingJoinService $joins): JsonResponse
    {
        $this->ensureScope($schoolClass, $meeting);
        $joinRequest = $joins->request($request->user(), $meeting);

        return response()->json(['request' => $this->payload($joinRequest)], $joinRequest->wasRecentlyCreated ? 201 : 200)
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, SchoolClass $schoolClass, ClassMeeting $meeting, ClassMeetingJoinService $joins): JsonResponse
    {
        $this->ensureScope($schoolClass, $meeting);
        Gate::authorize('view', $meeting);
        $joinRequest = $meeting->joinRequests()->where('requester_user_id', $request->user()->id)->firstOrFail();

        return response()->json(['request' => $this->payload($joins->cancel($request->user(), $joinRequest))])
            ->header('Cache-Control', 'no-store, private');
    }

    public function index(SchoolClass $schoolClass, ClassMeeting $meeting): JsonResponse
    {
        $this->ensureScope($schoolClass, $meeting);
        Gate::authorize('manageJoinRequests', $meeting);

        $requests = $meeting->joinRequests()->with('requester:id,name')
            ->where('status', ClassMeetingJoinRequest::PENDING)
            ->orderBy('requested_at')->limit(100)->get()
            ->map(fn (ClassMeetingJoinRequest $joinRequest): array => [
                'reference' => $joinRequest->public_uuid,
                'display_name' => $joinRequest->requester?->name ?? 'Class member',
                'requested_at' => $joinRequest->requested_at?->toIso8601String(),
            ]);

        return response()->json(['requests' => $requests])->header('Cache-Control', 'no-store, private');
    }

    public function update(DecideClassMeetingJoinRequestRequest $request, SchoolClass $schoolClass, ClassMeeting $meeting, ClassMeetingJoinRequest $joinRequest, ClassMeetingJoinService $joins): JsonResponse
    {
        $this->ensureScope($schoolClass, $meeting);
        abort_unless((int) $joinRequest->class_meeting_id === (int) $meeting->id, 404);
        $decided = $joins->decide($request->user(), $joinRequest, $request->validated('decision'));

        return response()->json(['request' => $this->payload($decided)])
            ->header('Cache-Control', 'no-store, private');
    }

    private function ensureScope(SchoolClass $schoolClass, ClassMeeting $meeting): void
    {
        abort_unless((int) $meeting->school_class_id === (int) $schoolClass->id, 404);
    }

    /** @return array{reference: string, status: string, requested_at: ?string, can_enter: bool}|null */
    private function payload(?ClassMeetingJoinRequest $joinRequest): ?array
    {
        if ($joinRequest === null) {
            return null;
        }

        return [
            'reference' => $joinRequest->public_uuid,
            'status' => $joinRequest->status,
            'requested_at' => $joinRequest->requested_at?->toIso8601String(),
            'can_enter' => $joinRequest->admitsCurrentEntry(),
        ];
    }
}
