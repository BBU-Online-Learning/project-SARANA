<?php

namespace App\Services;

use App\Models\ClassMeeting;
use App\Models\ClassMeetingJoinRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClassMeetingJoinService
{
    public function __construct(private LiveKitMeetingService $liveKit) {}

    public function request(User $actor, ClassMeeting $meeting): ClassMeetingJoinRequest
    {
        return DB::transaction(function () use ($actor, $meeting): ClassMeetingJoinRequest {
            $lockedMeeting = ClassMeeting::query()->lockForUpdate()->findOrFail($meeting->id);
            Gate::forUser($actor)->authorize('view', $lockedMeeting);
            abort_unless($this->liveKit->joinable($lockedMeeting), 403, 'This meeting is not open for joining.');
            abort_if(Gate::forUser($actor)->allows('manageJoinRequests', $lockedMeeting), 403);

            $joinRequest = $lockedMeeting->joinRequests()
                ->where('requester_user_id', $actor->id)->lockForUpdate()->first();

            if ($joinRequest === null) {
                return $lockedMeeting->joinRequests()->create([
                    'requester_user_id' => $actor->id,
                    'status' => ClassMeetingJoinRequest::PENDING,
                    'requested_at' => now(),
                ]);
            }

            abort_if($joinRequest->status === ClassMeetingJoinRequest::REMOVED, 403, 'The teacher removed you from this meeting.');

            if ($joinRequest->status !== ClassMeetingJoinRequest::PENDING && ! $joinRequest->admitsCurrentEntry()) {
                $joinRequest->update([
                    'status' => ClassMeetingJoinRequest::PENDING,
                    'requested_at' => now(),
                    'decided_at' => null,
                    'decided_by' => null,
                ]);
            }

            return $joinRequest;
        });
    }

    public function cancel(User $actor, ClassMeetingJoinRequest $joinRequest): ClassMeetingJoinRequest
    {
        return DB::transaction(function () use ($actor, $joinRequest): ClassMeetingJoinRequest {
            $locked = ClassMeetingJoinRequest::query()->lockForUpdate()->findOrFail($joinRequest->id);
            Gate::forUser($actor)->authorize('view', $locked->meeting);
            abort_unless((int) $locked->requester_user_id === (int) $actor->id, 403);

            if ($locked->status !== ClassMeetingJoinRequest::PENDING) {
                throw ValidationException::withMessages(['request' => 'Only a pending request can be cancelled.']);
            }

            $locked->update(['status' => ClassMeetingJoinRequest::CANCELLED]);

            return $locked;
        });
    }

    public function decide(User $actor, ClassMeetingJoinRequest $joinRequest, string $decision): ClassMeetingJoinRequest
    {
        return DB::transaction(function () use ($actor, $joinRequest, $decision): ClassMeetingJoinRequest {
            $locked = ClassMeetingJoinRequest::query()->lockForUpdate()->findOrFail($joinRequest->id);
            Gate::forUser($actor)->authorize('manageJoinRequests', $locked->meeting);
            abort_unless($this->liveKit->joinable($locked->meeting), 403, 'This meeting is not open for joining.');

            if ($locked->status !== ClassMeetingJoinRequest::PENDING) {
                throw ValidationException::withMessages(['request' => 'This join request has already been decided.']);
            }

            $locked->update([
                'status' => $decision,
                'decided_at' => now(),
                'decided_by' => $actor->id,
            ]);

            return $locked;
        });
    }
}
