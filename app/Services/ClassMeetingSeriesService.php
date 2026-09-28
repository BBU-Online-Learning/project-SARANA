<?php

namespace App\Services;

use App\Models\ClassMeeting;
use App\Models\ClassMeetingSeries;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassMeetingSeriesService
{
    public function __construct(private ClassManagementService $classes, private ClassMeetingService $meetings) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, SchoolClass $schoolClass, array $data): ClassMeeting
    {
        $meeting = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($data): ClassMeeting {
            Gate::forUser($actor)->authorize('create', [ClassMeeting::class, $schoolClass]);
            $timezone = config('app.timezone');
            $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], $timezone);
            $end = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], $timezone);
            $series = $schoolClass->meetingSeries()->create([
                'created_by' => $actor->id,
                'series_key' => (string) Str::uuid(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'recurrence' => $data['recurrence'],
                'weekdays' => $data['recurrence'] === 'selected_weekdays' ? array_map('intval', $data['weekdays']) : null,
                'starts_on' => $start->toDateString(),
                'ends_on' => $data['repeat_until'] ?? null,
                'local_start_time' => $start->format('H:i:s'),
                'duration_minutes' => (int) $start->diffInMinutes($end),
                'timezone' => $timezone,
                'status' => 'active',
            ]);
            $through = CarbonImmutable::today($timezone)->addDays(60)->max($start->addDays(60)->startOfDay());
            $this->generateLocked($series, $through);

            $first = $series->occurrences()->orderBy('starts_at')->first();
            if ($first === null) {
                throw ValidationException::withMessages(['weekdays' => 'No meeting falls on the selected weekdays before the repeat end date.']);
            }

            return $first;
        });

        $this->meetings->notifyMembers($meeting, $actor, 'scheduled');

        return $meeting;
    }

    public function replenish(ClassMeetingSeries $series, CarbonImmutable $through): int
    {
        return ClassMeetingSeries::resolveConnection()->transaction(function () use ($series, $through): int {
            $locked = ClassMeetingSeries::query()->lockForUpdate()->findOrFail($series->id);
            if ($locked->status !== 'active' || $locked->schoolClass->isArchived()) {
                return 0;
            }
            if ($locked->ends_on !== null && $locked->ends_on->toDateString() < CarbonImmutable::today($locked->timezone)->toDateString()) {
                $locked->update(['status' => 'completed']);

                return 0;
            }

            return $this->generateLocked($locked, $through);
        });
    }

    public function cancel(User $actor, SchoolClass $schoolClass, ClassMeetingSeries $series): bool
    {
        $first = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($series): ?ClassMeeting {
            $locked = $schoolClass->meetingSeries()->lockForUpdate()->findOrFail($series->id);
            Gate::forUser($actor)->authorize('create', [ClassMeeting::class, $schoolClass]);
            if ($locked->status === 'cancelled') {
                return null;
            }
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id]);
            $first = $locked->occurrences()->where('status', 'scheduled')->where('starts_at', '>', now())
                ->orderBy('starts_at')->first();
            $locked->occurrences()->where('status', 'scheduled')->where('starts_at', '>', now())
                ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id]);

            return $first;
        });

        if ($first !== null) {
            $this->meetings->notifyMembers($first, $actor, 'series_cancelled');
        }

        return $first !== null;
    }

    private function generateLocked(ClassMeetingSeries $series, CarbonImmutable $through): int
    {
        $timezone = $series->timezone;
        $start = CarbonImmutable::parse($series->starts_on->toDateString(), $timezone);
        $today = CarbonImmutable::today($timezone);
        $cursor = $series->generated_through
            ? CarbonImmutable::parse($series->generated_through->toDateString(), $timezone)->addDay()
            : $start;
        $cursor = $cursor->max($start, $today);
        $last = $through->setTimezone($timezone)->startOfDay();
        if ($series->ends_on) {
            $last = $last->min(CarbonImmutable::parse($series->ends_on->toDateString(), $timezone));
        }
        $created = 0;
        $number = (int) $series->occurrences()->max('occurrence_number');

        for ($date = $cursor; $date->lessThanOrEqualTo($last); $date = $date->addDay()) {
            if (! $this->matches($series, $date, $start)) {
                continue;
            }
            $startsAt = CarbonImmutable::parse($date->toDateString().' '.$series->local_start_time, $timezone);
            $number++;
            $series->occurrences()->create([
                'school_class_id' => $series->school_class_id,
                'created_by' => $series->created_by,
                'series_key' => $series->series_key,
                'title' => $series->title,
                'description' => $series->description,
                'recurrence' => $series->recurrence === 'selected_weekdays' ? 'weekly' : $series->recurrence,
                'occurrence_number' => $number,
                'occurrence_count' => 1,
                'series_occurrence_on' => $date->toDateString(),
                'original_starts_at' => $startsAt,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($series->duration_minutes),
                'status' => 'scheduled',
            ]);
            $created++;
        }

        if ($last->greaterThanOrEqualTo($start) && ($series->generated_through === null || $last->greaterThan($series->generated_through))) {
            $series->update(['generated_through' => $last->toDateString()]);
        }

        return $created;
    }

    private function matches(ClassMeetingSeries $series, CarbonImmutable $date, CarbonImmutable $start): bool
    {
        return match ($series->recurrence) {
            'daily' => true,
            'weekly' => $date->dayOfWeekIso === $start->dayOfWeekIso,
            'selected_weekdays' => in_array($date->dayOfWeekIso, $series->weekdays ?? [], true),
            default => false,
        };
    }
}
