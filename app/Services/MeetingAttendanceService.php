<?php

namespace App\Services;

use App\Models\ClassMeeting;
use App\Models\MeetingAttendanceSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livekit\WebhookEvent;

class MeetingAttendanceService
{
    public function record(WebhookEvent $event): void
    {
        $eventType = $event->getEvent();
        if (! in_array($eventType, ['participant_joined', 'participant_left', 'room_finished'], true)) {
            return;
        }

        $room = $event->getRoom();
        $roomName = $room?->getName();
        $roomSid = $room?->getSid();
        if (! $event->getId() || ! $roomSid || ! preg_match('/^class-(\d+)-meeting-(\d+)$/', (string) $roomName, $matches)) {
            return;
        }

        $occurredAt = (int) $event->getCreatedAt();
        if ($occurredAt <= 0) {
            return;
        }

        $timestamp = CarbonImmutable::createFromTimestamp($occurredAt, config('app.timezone'));
        DB::transaction(function () use ($event, $eventType, $roomSid, $matches, $timestamp): void {
            $meeting = ClassMeeting::query()->whereKey((int) $matches[2])->lockForUpdate()->first();
            if (! $meeting || $meeting->school_class_id !== (int) $matches[1]) {
                return;
            }

            $participant = $event->getParticipant();
            $participantSid = $participant?->getSid();
            $identity = $participant?->getIdentity();
            $user = null;
            if ($eventType !== 'room_finished') {
                if (! $participantSid || ! preg_match('/^user-(\d+)$/', (string) $identity, $userMatches)) {
                    return;
                }
                $user = User::withTrashed()->find((int) $userMatches[1]);
                if (! $user) {
                    return;
                }
            }

            $inserted = DB::table('meeting_attendance_webhook_events')->insertOrIgnore([
                'id' => $event->getId(), 'class_meeting_id' => $meeting->id,
                'room_sid' => $roomSid, 'event' => $eventType,
                'occurred_at' => $timestamp, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                return;
            }

            if ($eventType === 'room_finished') {
                $meeting->attendanceSessions()->where('room_sid', $roomSid)
                    ->whereNotNull('joined_at')->where('joined_at', '<=', $timestamp)
                    ->where(function ($query) use ($timestamp): void {
                        $query->whereNull('left_at')->orWhere('left_at', '>', $timestamp);
                    })->update(['left_at' => $timestamp, 'leave_reason' => 'room_finished']);

                return;
            }

            $session = $meeting->attendanceSessions()->where('participant_sid', $participantSid)->first();
            if ($session && ((int) $session->user_id !== (int) $user->id || $session->room_sid !== $roomSid)) {
                return;
            }
            if (! $session) {
                $session = new MeetingAttendanceSession([
                    'user_id' => $user->id, 'room_sid' => $roomSid, 'participant_sid' => $participantSid,
                ]);
                $session->meeting()->associate($meeting);
            }

            if ($eventType === 'participant_joined') {
                if (! $session->joined_at || $timestamp->lessThan($session->joined_at)) {
                    $session->joined_at = $timestamp;
                }
                $roomClosedAt = DB::table('meeting_attendance_webhook_events')
                    ->where('class_meeting_id', $meeting->id)->where('room_sid', $roomSid)
                    ->where('event', 'room_finished')->where('occurred_at', '>=', $session->joined_at)
                    ->min('occurred_at');
                if ($roomClosedAt) {
                    $this->closeSession($session, CarbonImmutable::parse($roomClosedAt, config('app.timezone')), 'room_finished');
                }
                if ($meeting->ended_at) {
                    $this->closeSession($session, $meeting->ended_at->toImmutable(), 'meeting_ended');
                }
            } else {
                $this->closeSession($session, $timestamp, 'participant_left');
            }

            $session->save();
        });
    }

    public function closeForMeeting(ClassMeeting $meeting, CarbonImmutable $endedAt): void
    {
        $meeting->attendanceSessions()->whereNotNull('joined_at')->whereNull('left_at')
            ->update(['left_at' => $endedAt, 'leave_reason' => 'meeting_ended']);
    }

    private function closeSession(MeetingAttendanceSession $session, CarbonImmutable $timestamp, string $reason): void
    {
        if (! $session->left_at || $timestamp->lessThan($session->left_at)) {
            $session->left_at = $timestamp;
            $session->leave_reason = $reason;
        }
    }
}
