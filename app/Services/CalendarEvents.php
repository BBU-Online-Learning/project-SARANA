<?php

namespace App\Services;

use App\Models\ClassMeeting;
use App\Models\CourseworkAssignment;
use App\Models\QuizAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalendarEvents
{
    private const MAX_PER_TYPE = 250;

    /**
     * @return array{events: Collection<int, array{id: string, type: string, title: string, class_name: string, at: CarbonImmutable, url: string}>, limited: bool}
     */
    public function for(User $user, CarbonImmutable $month): array
    {
        $timezone = config('app.timezone');
        $start = $month->startOfMonth()->toDateTimeString();
        $end = $month->startOfMonth()->addMonth()->toDateTimeString();
        $teacher = $user->role->name === 'teacher';
        $student = $user->role->name === 'student';
        if (! $teacher && ! $student) {
            return ['events' => collect(), 'limited' => false];
        }

        $role = $teacher ? ['owner', 'teacher'] : ['student'];
        $membership = fn ($query) => $query->where('user_id', $user->id)->whereIn('role', $role);

        $quizzes = QuizAssignment::query()
            ->where('due_at', '>=', $start)->where('due_at', '<', $end)
            ->where('status', '!=', 'cancelled')
            ->whereHas('quiz')
            ->whereHas('schoolClass.memberRecords', $membership)
            ->when($student, fn ($query) => $query->whereHas('students', fn ($students) => $students->whereKey($user->id)))
            ->with(['quiz:id,title', 'schoolClass:id,name'])
            ->orderBy('due_at')->orderBy('id')->limit(self::MAX_PER_TYPE + 1)->get();

        $coursework = CourseworkAssignment::query()
            ->where('due_at', '>=', $start)->where('due_at', '<', $end)
            ->whereIn('status', $teacher ? ['draft', 'published', 'closed'] : ['published', 'closed'])
            ->whereHas('schoolClass.memberRecords', $membership)
            ->with('schoolClass:id,name')
            ->orderBy('due_at')->orderBy('id')->limit(self::MAX_PER_TYPE + 1)->get();

        $meetings = ClassMeeting::query()
            ->where('starts_at', '>=', $start)->where('starts_at', '<', $end)
            ->where('status', 'scheduled')
            ->whereHas('schoolClass.memberRecords', $membership)
            ->with('schoolClass:id,name')
            ->orderBy('starts_at')->orderBy('id')->limit(self::MAX_PER_TYPE + 1)->get();

        $limited = $quizzes->count() > self::MAX_PER_TYPE || $coursework->count() > self::MAX_PER_TYPE
            || $meetings->count() > self::MAX_PER_TYPE;
        $events = $quizzes->take(self::MAX_PER_TYPE)->map(fn (QuizAssignment $assignment): array => [
            'id' => 'quiz:'.$assignment->id,
            'type' => 'Quiz deadline',
            'title' => $assignment->quiz->title,
            'class_name' => $assignment->schoolClass->name,
            'at' => $assignment->due_at->toImmutable()->setTimezone($timezone),
            'url' => route('classes.assessments.index', $assignment->school_class_id),
        ])->concat($coursework->take(self::MAX_PER_TYPE)->map(fn (CourseworkAssignment $assignment): array => [
            'id' => 'coursework:'.$assignment->id,
            'type' => $assignment->status === 'draft' ? 'Draft coursework deadline' : 'Coursework deadline',
            'title' => $assignment->title,
            'class_name' => $assignment->schoolClass->name,
            'at' => $assignment->due_at->toImmutable()->setTimezone($timezone),
            'url' => route('classes.coursework.assignments.show', [$assignment->school_class_id, $assignment->id]),
        ]))->concat($meetings->take(self::MAX_PER_TYPE)->map(fn (ClassMeeting $meeting): array => [
            'id' => 'meeting:'.$meeting->id,
            'type' => 'Class meeting',
            'title' => $meeting->title,
            'class_name' => $meeting->schoolClass->name,
            'at' => $meeting->starts_at->toImmutable()->setTimezone($timezone),
            'url' => route('classes.meetings.show', [$meeting->school_class_id, $meeting->id]),
        ]))->sortBy(fn (array $event): string => $event['at']->format('Y-m-d H:i:s').':'.$event['id'])->values();

        return compact('events', 'limited');
    }
}
