<?php

namespace App\Http\Controllers;

use App\Models\ClassMeeting;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassMeetingModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClassMeetingModerationController extends Controller
{
    public function end(Request $request, SchoolClass $schoolClass, ClassMeeting $meeting, ClassMeetingModerationService $moderation): JsonResponse|RedirectResponse
    {
        $this->ensureScope($schoolClass, $meeting);
        $moderation->end($request->user(), $schoolClass, $meeting);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ended'])->header('Cache-Control', 'no-store, private');
        }

        return redirect()->route('classes.meetings.show', [$schoolClass, $meeting])->with('success', 'Meeting ended for everyone.');
    }

    public function removeParticipant(Request $request, SchoolClass $schoolClass, ClassMeeting $meeting, User $user, ClassMeetingModerationService $moderation): JsonResponse
    {
        $this->ensureScope($schoolClass, $meeting);
        $moderation->removeParticipant($request->user(), $schoolClass, $meeting, $user);

        return response()->json(['status' => 'removed'])->header('Cache-Control', 'no-store, private');
    }

    private function ensureScope(SchoolClass $schoolClass, ClassMeeting $meeting): void
    {
        abort_unless((int) $meeting->school_class_id === (int) $schoolClass->id, 404);
    }
}
