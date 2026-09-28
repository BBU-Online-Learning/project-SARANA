<?php

use App\Models\ClassMeeting;
use App\Models\CourseworkAssignment;
use App\Models\Quiz;
use App\Models\QuizAssignment;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CalendarEvents;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

function calendarUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function calendarClass(User $teacher, User ...$students): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    foreach ($students as $student) {
        $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    }

    return $schoolClass;
}

function calendarQuiz(SchoolClass $schoolClass, User $teacher, string $title, string $dueAt, User ...$students): QuizAssignment
{
    $quiz = Quiz::query()->create([
        'school_class_id' => $schoolClass->id,
        'creator_id' => $teacher->id,
        'title' => $title,
        'status' => 'published',
        'published_at' => now(),
    ]);
    $assignment = QuizAssignment::query()->create([
        'quiz_id' => $quiz->id,
        'school_class_id' => $schoolClass->id,
        'assigned_by' => $teacher->id,
        'starts_at' => CarbonImmutable::parse($dueAt)->subDay(),
        'due_at' => $dueAt,
        'status' => 'scheduled',
    ]);
    $assignment->students()->attach(collect($students)->pluck('id')->all(), ['assigned_at' => now()]);

    return $assignment;
}

function calendarCoursework(SchoolClass $schoolClass, User $teacher, string $title, string $dueAt, string $status = 'published'): CourseworkAssignment
{
    return CourseworkAssignment::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'title' => $title,
        'due_at' => $dueAt,
        'status' => $status,
    ]);
}

test('calendar shows only authorized quiz and coursework deadlines with named route links', function () {
    $teacher = calendarUser('teacher');
    $student = calendarUser('student');
    $otherStudent = calendarUser('student');
    $otherTeacher = calendarUser('teacher');
    $schoolClass = calendarClass($teacher, $student, $otherStudent);
    $otherClass = calendarClass($otherTeacher);
    $ownQuiz = calendarQuiz($schoolClass, $teacher, 'Visible quiz deadline', '2026-09-14 10:00:00', $student);
    calendarQuiz($schoolClass, $teacher, 'Other student quiz', '2026-09-15 10:00:00', $otherStudent);
    calendarQuiz($schoolClass, $teacher, 'Cancelled quiz deadline', '2026-09-17 10:00:00', $student)->update(['status' => 'cancelled']);
    calendarQuiz($otherClass, $otherTeacher, 'Other class quiz', '2026-09-16 10:00:00');
    $published = calendarCoursework($schoolClass, $teacher, 'Visible coursework deadline', '2026-09-18 15:30:00');
    calendarCoursework($schoolClass, $teacher, 'Teacher draft deadline', '2026-09-19 15:30:00', 'draft');
    calendarCoursework($otherClass, $otherTeacher, 'Other class coursework', '2026-09-20 15:30:00');

    $this->actingAs($student)->get(route('calendar.index', ['month' => '2026-09']))
        ->assertOk()->assertSee('Visible quiz deadline')->assertSee('Visible coursework deadline')
        ->assertSee(route('classes.assessments.index', $schoolClass), false)
        ->assertSee(route('classes.coursework.assignments.show', [$schoolClass, $published]), false)
        ->assertDontSee('Other student quiz')->assertDontSee('Teacher draft deadline')->assertDontSee('Cancelled quiz deadline')->assertDontSee('Other class coursework');

    $this->actingAs($teacher)->get(route('calendar.index', ['month' => '2026-09']))
        ->assertOk()->assertSee('Visible quiz deadline')->assertSee('Other student quiz')->assertSee('Teacher draft deadline')->assertSee('Draft coursework deadline')
        ->assertDontSee('Cancelled quiz deadline')
        ->assertDontSee('Other class quiz')->assertDontSee('Other class coursework');
    expect($ownQuiz->students()->count())->toBe(1);
});

test('mobile agenda groups authorized events by local date while desktop grid remains available', function (): void {
    $teacher = calendarUser('teacher');
    $student = calendarUser('student');
    $otherStudent = calendarUser('student');
    $schoolClass = calendarClass($teacher, $student, $otherStudent);
    calendarQuiz($schoolClass, $teacher, 'Morning quiz', '2026-09-14 09:00:00', $student);
    calendarQuiz($schoolClass, $teacher, 'Private quiz', '2026-09-14 10:00:00', $otherStudent);
    $meeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'title' => 'Biology discussion',
        'starts_at' => '2026-09-14 11:00:00',
        'ends_at' => '2026-09-14 12:00:00',
        'original_starts_at' => '2026-09-14 11:00:00',
    ]);
    $coursework = calendarCoursework($schoolClass, $teacher, 'Written reflection', '2026-09-15 15:30:00');

    $this->actingAs($student)->get(route('calendar.index', ['month' => '2026-09']))->assertOk()
        ->assertViewHas('eventsByDay', fn (Collection $days): bool => $days->keys()->all() === ['2026-09-14', '2026-09-15']
            && $days->get('2026-09-14')->pluck('title')->all() === ['Morning quiz', 'Biology discussion'])
        ->assertSee('class="table-responsive calendar-month-grid"', false)
        ->assertSee('class="calendar-legend"', false)
        ->assertSee('class="calendar-type calendar-type--meeting"', false)
        ->assertSeeInOrder([
            '<section class="calendar-agenda" aria-label="Agenda for September 2026">',
            'id="calendar-agenda-day-2026-09-14"',
            '2 events',
            'Morning quiz',
            'Biology discussion',
            'id="calendar-agenda-day-2026-09-15"',
            '1 event',
            'Written reflection',
        ], false)
        ->assertSee('datetime="2026-09-14T09:00:00+07:00"', false)
        ->assertSee(route('classes.meetings.show', [$schoolClass, $meeting]), false)
        ->assertSee(route('classes.coursework.assignments.show', [$schoolClass, $coursework]), false)
        ->assertDontSee('Private quiz');
});

test('calendar keeps a clear empty state for a month without agenda events', function (): void {
    $teacher = calendarUser('teacher');

    $this->actingAs($teacher)->get(route('calendar.index', ['month' => '2026-09']))->assertOk()
        ->assertSee('aria-label="Agenda for September 2026"', false)
        ->assertSee('No deadlines or meetings are available to you this month.');
});

test('calendar uses half open month boundaries in the configured timezone', function () {
    $teacher = calendarUser('teacher');
    $student = calendarUser('student');
    $schoolClass = calendarClass($teacher, $student);
    calendarCoursework($schoolClass, $teacher, 'Before September', '2026-08-31 23:59:59');
    calendarCoursework($schoolClass, $teacher, 'Start of September', '2026-09-01 00:00:00');
    calendarCoursework($schoolClass, $teacher, 'End of September', '2026-09-30 23:59:59');
    calendarCoursework($schoolClass, $teacher, 'Start of October', '2026-10-01 00:00:00');

    $calendar = app(CalendarEvents::class)->for($student, CarbonImmutable::parse('2026-09-01', config('app.timezone')));
    expect($calendar['events']->pluck('title')->all())->toBe(['Start of September', 'End of September'])
        ->and($calendar['events']->first()['at']->timezoneName)->toBe('Asia/Bangkok');
    $this->actingAs($student)->get(route('calendar.index', ['month' => '2026-09']))
        ->assertOk()->assertSee('Asia/Bangkok time')->assertSee('12:00 AM')
        ->assertSee(route('calendar.index', ['month' => '2026-08']), false)
        ->assertSee(route('calendar.index', ['month' => '2026-10']), false)
        ->assertDontSee('Before September')->assertDontSee('Start of October');
});

test('calendar hides deadlines after access is removed and rejects invalid months', function () {
    $teacher = calendarUser('teacher');
    $student = calendarUser('student');
    $schoolClass = calendarClass($teacher, $student);
    calendarQuiz($schoolClass, $teacher, 'Revoked quiz deadline', '2026-09-14 10:00:00', $student);
    calendarCoursework($schoolClass, $teacher, 'Revoked coursework deadline', '2026-09-15 10:00:00');
    $schoolClass->memberRecords()->where('user_id', $student->id)->delete();

    $this->actingAs($student)->get(route('calendar.index', ['month' => '2026-09']))
        ->assertOk()->assertDontSee('Revoked quiz deadline')->assertDontSee('Revoked coursework deadline');
    $this->actingAs($student)->get(route('calendar.index', ['month' => '2026-13']))->assertSessionHasErrors('month');
    auth()->logout();
    $this->get(route('calendar.index', ['month' => '2026-09']))->assertRedirect(route('login'));
});

test('calendar caps each event type after authorization filters', function () {
    $teacher = calendarUser('teacher');
    $schoolClass = calendarClass($teacher);
    $rows = [];
    for ($number = 1; $number <= 251; $number++) {
        $rows[] = [
            'school_class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
            'title' => 'Bounded deadline '.$number,
            'instructions' => null,
            'max_points' => 100,
            'due_at' => '2026-09-20 09:00:00',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    CourseworkAssignment::query()->insert($rows);

    $calendar = app(CalendarEvents::class)->for($teacher, CarbonImmutable::parse('2026-09-01', config('app.timezone')));
    expect($calendar['events'])->toHaveCount(250)->and($calendar['limited'])->toBeTrue();
    $this->actingAs($teacher)->get(route('calendar.index', ['month' => '2026-09']))
        ->assertOk()->assertSee('first 250 of each type');
});
