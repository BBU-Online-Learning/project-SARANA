<?php

namespace App\Services;

use App\Models\ClassMeeting;
use App\Models\ClassMeetingJoinRequest;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClassMeetingModerationService
{
    public function __construct(private ClassManagementService $classes, private LiveKitRoomControl $rooms, private MeetingAttendanceService $attendance) {}

    public function end(User $actor, SchoolClass $schoolClass, ClassMeeting $meeting): ClassMeeting
    {
        $ending = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($meeting): ClassMeeting {
            $locked = $schoolClass->meetings()->lockForUpdate()->findOrFail($meeting->id);
            Gate::forUser($actor)->authorize('end', $locked);
            if ($locked->status === 'scheduled') {
                $locked->update(['status' => 'ending']);
            }

            return $locked;
        });

        try {
            $this->rooms->end($ending);
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'LiveKit could not close the room. Try ending the meeting again.');
        }

        return $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($meeting): ClassMeeting {
            $locked = $schoolClass->meetings()->lockForUpdate()->findOrFail($meeting->id);
            if ($locked->status === 'ending') {
                $locked->update(['status' => 'ended', 'ended_at' => now(), 'ended_by' => $actor->id]);
                $this->attendance->closeForMeeting($locked, $locked->ended_at->toImmutable());
            }

            return $locked;
        });
    }

    public function removeParticipant(User $actor, SchoolClass $schoolClass, ClassMeeting $meeting, User $target): void
    {
        $locked = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($meeting, $target): ClassMeeting {
            $locked = $schoolClass->meetings()->lockForUpdate()->findOrFail($meeting->id);
            Gate::forUser($actor)->authorize('removeParticipant', [$locked, $target]);
            $joinRequest = $locked->joinRequests()->where('requester_user_id', $target->id)->lockForUpdate()->firstOrFail();
            if (! in_array($joinRequest->status, [ClassMeetingJoinRequest::ADMITTED, ClassMeetingJoinRequest::REMOVED], true)) {
                throw ValidationException::withMessages(['participant' => 'Only admitted students can be removed.']);
            }

            if ($joinRequest->status !== ClassMeetingJoinRequest::REMOVED) {
                $joinRequest->update([
                    'status' => ClassMeetingJoinRequest::REMOVED,
                    'decided_at' => now(),
                    'decided_by' => $actor->id,
                ]);
            }

            return $locked;
        });

        try {
            $this->rooms->removeParticipant($locked, $target->id);
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'The student cannot rejoin, but LiveKit could not disconnect them yet. Try removing them again.');
        }
    }
}
