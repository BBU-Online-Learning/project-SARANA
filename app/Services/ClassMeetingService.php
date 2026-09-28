<?php

namespace App\Services;

use App\Models\ClassMeeting;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ClassMeetingService
{
    public function __construct(private ClassManagementService $classes, private ClassAccessService $access) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, SchoolClass $schoolClass, array $data): ClassMeeting
    {
        $meeting = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($data): ClassMeeting {
            Gate::forUser($actor)->authorize('create', [ClassMeeting::class, $lockedClass]);
            $startsAt = $this->localTime($data['starts_at']);
            $endsAt = $this->localTime($data['ends_at']);
            $seriesKey = (string) Str::uuid();
            $count = (int) $data['occurrence_count'];
            $first = null;

            for ($number = 1; $number <= $count; $number++) {
                $shift = $data['recurrence'] === 'daily' ? $number - 1 : ($data['recurrence'] === 'weekly' ? ($number - 1) * 7 : 0);
                $occurrenceStarts = $startsAt->addDays($shift);
                $occurrenceEnds = $endsAt->addDays($shift);
                $meeting = $lockedClass->meetings()->create([
                    'created_by' => $actor->id,
                    'series_key' => $seriesKey,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'recurrence' => $data['recurrence'],
                    'occurrence_number' => $number,
                    'occurrence_count' => $count,
                    'original_starts_at' => $occurrenceStarts,
                    'starts_at' => $occurrenceStarts,
                    'ends_at' => $occurrenceEnds,
                    'status' => 'scheduled',
                ]);
                $first ??= $meeting;
            }

            return $first;
        });

        $this->notifyMembers($meeting, $actor, 'scheduled');

        return $meeting;
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, SchoolClass $schoolClass, ClassMeeting $meeting, array $data): ClassMeeting
    {
        $change = null;
        $meeting = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($meeting, $data, &$change): ClassMeeting {
            $locked = $lockedClass->meetings()->lockForUpdate()->findOrFail($meeting->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $startsAt = $this->localTime($data['starts_at']);
            $endsAt = $this->localTime($data['ends_at']);
            $rescheduled = ! $locked->starts_at->equalTo($startsAt) || ! $locked->ends_at->equalTo($endsAt);
            $detailsChanged = $locked->title !== $data['title'] || $locked->description !== ($data['description'] ?? null);
            if (! $rescheduled && ! $detailsChanged) {
                return $locked;
            }

            $locked->update([
                'title' => $data['title'], 'description' => $data['description'] ?? null,
                'starts_at' => $startsAt, 'ends_at' => $endsAt,
                'rescheduled_at' => $rescheduled ? now() : $locked->rescheduled_at,
                'rescheduled_by' => $rescheduled ? $actor->id : $locked->rescheduled_by,
                'series_override_at' => $locked->class_meeting_series_id ? now() : null,
            ]);
            $change = $rescheduled ? 'rescheduled' : 'updated';

            return $locked;
        });

        if ($change !== null) {
            $this->notifyMembers($meeting, $actor, $change);
        }

        return $meeting;
    }

    public function cancel(User $actor, SchoolClass $schoolClass, ClassMeeting $meeting): ClassMeeting
    {
        $meeting = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $lockedClass) use ($meeting): ClassMeeting {
            $locked = $lockedClass->meetings()->lockForUpdate()->findOrFail($meeting->id);
            Gate::forUser($actor)->authorize('cancel', $locked);
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id,
                'series_override_at' => $locked->class_meeting_series_id ? now() : null]);

            return $locked;
        });

        $this->notifyMembers($meeting, $actor, 'cancelled');

        return $meeting;
    }

    public function notifyMembers(ClassMeeting $meeting, User $actor, string $change): void
    {
        $schoolClass = $meeting->schoolClass()->first();
        if (! $schoolClass) {
            return;
        }

        $title = match ($change) {
            'scheduled' => 'Class meeting scheduled',
            'rescheduled' => 'Class meeting rescheduled',
            'updated' => 'Class meeting updated',
            'cancelled' => 'Class meeting cancelled',
            'series_cancelled' => 'Recurring class meetings cancelled',
        };
        $body = $meeting->title.' · '.$schoolClass->name.' · '.$meeting->starts_at->format('d M Y, g:i A').' '.config('app.timezone');
        if ($change === 'scheduled' && $meeting->occurrence_count > 1) {
            $body .= ' · '.$meeting->occurrence_count.' '.$meeting->recurrence.' meetings';
        } elseif ($change === 'scheduled' && $meeting->class_meeting_series_id) {
            $body .= ' · recurring '.$meeting->recurrence.' meetings';
        }

        $schoolClass->members()->with('role')->where('users.id', '!=', $actor->id)
            ->whereIn('school_class_members.role', ['owner', 'teacher', 'student'])->get()
            ->each(function (User $member) use ($meeting, $schoolClass, $title, $body): void {
                if (! $this->access->ready($member)) {
                    return;
                }

                $member->notify(new ActivityNotification(
                    'class', $title, $body,
                    route('classes.meetings.show', [$schoolClass, $meeting], false),
                ));
            });
    }

    private function localTime(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, config('app.timezone'));
    }
}
