<?php

use App\Models\ClassMeeting;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CalendarEvents;
use Carbon\CarbonImmutable;
use Database\Seeders\ClassMeetingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function meetingUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function meetingClass(User $teacher, User ...$students): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    foreach ($students as $student) {
        $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    }

    return $schoolClass;
}

/** @return array<string, mixed> */
function meetingForm(CarbonImmutable $start, string $recurrence = 'none', int $count = 1): array
{
    return [
        'title' => 'Biology class meeting', 'description' => 'Chapter review',
        'starts_at' => $start->format('Y-m-d\TH:i'),
        'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
        'recurrence' => $recurrence, 'occurrence_count' => $count,
    ];
}

test('teacher creates a finite weekly series visible to current students and calendar', function (): void {
    $teacher = meetingUser('teacher');
    $student = meetingUser('student');
    $schoolClass = meetingClass($teacher, $student);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();

    $this->actingAs($teacher)->post(route('classes.meetings.store', $schoolClass), meetingForm($start, 'weekly', 3))
        ->assertRedirect()->assertSessionHasNoErrors();
    $meetings = $schoolClass->meetings()->orderBy('occurrence_number')->get();
    expect($meetings)->toHaveCount(3)
        ->and($meetings->pluck('series_key')->unique())->toHaveCount(1)
        ->and($meetings->pluck('occurrence_number')->all())->toBe([1, 2, 3])
        ->and($meetings[1]->starts_at->toDateTimeString())->toBe($start->addWeek()->toDateTimeString())
        ->and($meetings[2]->starts_at->toDateTimeString())->toBe($start->addWeeks(2)->toDateTimeString())
        ->and($student->notifications()->count())->toBe(1)
        ->and($teacher->notifications()->count())->toBe(0);
    expect($student->notifications()->first()->data['title'])->toBe('Class meeting scheduled')
        ->and($student->notifications()->first()->data['body'])->toContain('3 weekly meetings')
        ->and($student->notifications()->first()->data['url'])->toBe(route('classes.meetings.show', [$schoolClass, $meetings[0]], false));

    $this->actingAs($student)->get(route('classes.meetings.index', $schoolClass))->assertOk()
        ->assertSee('Biology class meeting')->assertSee('Weekly · Meeting 1 of 3')
        ->assertSee('class="meeting-date-tile"', false)
        ->assertSee('class="class-status class-status--info"', false)
        ->assertDontSee('>Join meeting<', false);
    $this->actingAs($student)->get(route('classes.meetings.show', [$schoolClass, $meetings[0]]))->assertOk()
        ->assertSee('Chapter review')->assertSee('Online joining is not available')
        ->assertDontSee('Save changes')->assertDontSee('>Join meeting<', false);
    $calendar = app(CalendarEvents::class)->for($student, $start->startOfMonth());
    expect($calendar['events']->pluck('id'))->toContain('meeting:'.$meetings[0]->id)
        ->and($calendar['events']->firstWhere('id', 'meeting:'.$meetings[0]->id)['url'])
        ->toBe(route('classes.meetings.show', [$schoolClass, $meetings[0]]));
    $this->actingAs($student)->get(route('calendar.index', ['month' => $start->format('Y-m')]))
        ->assertOk()->assertSee('Biology class meeting')->assertSee('Class meeting');
});

test('teacher reschedules and cancels only selected occurrences', function (): void {
    $teacher = meetingUser('teacher');
    $student = meetingUser('student');
    $schoolClass = meetingClass($teacher, $student);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();
    $this->actingAs($teacher)->post(route('classes.meetings.store', $schoolClass), meetingForm($start, 'daily', 3))->assertRedirect();
    $meetings = $schoolClass->meetings()->orderBy('occurrence_number')->get();
    $newStart = $start->addHours(3);
    $updated = meetingForm($newStart);
    $updated['title'] = 'Moved biology meeting';
    unset($updated['recurrence'], $updated['occurrence_count']);
    $this->patch(route('classes.meetings.update', [$schoolClass, $meetings[0]]), $updated)->assertRedirect()->assertSessionHasNoErrors();
    expect($meetings[0]->fresh()->starts_at->toDateTimeString())->toBe($newStart->toDateTimeString())
        ->and($meetings[0]->fresh()->original_starts_at->toDateTimeString())->toBe($start->toDateTimeString())
        ->and($meetings[0]->fresh()->rescheduled_by)->toBe($teacher->id)
        ->and($meetings[1]->fresh()->starts_at->toDateTimeString())->toBe($start->addDay()->toDateTimeString())
        ->and($student->notifications()->count())->toBe(2);
    expect($student->notifications()->get()->map(fn ($notification): string => $notification->data['title'])->all())
        ->toContain('Class meeting rescheduled');

    $this->patch(route('classes.meetings.update', [$schoolClass, $meetings[0]]), $updated)->assertRedirect()->assertSessionHasNoErrors();
    expect($student->notifications()->count())->toBe(2);

    $this->post(route('classes.meetings.cancel', [$schoolClass, $meetings[1]]))->assertRedirect();
    expect($meetings[1]->fresh()->status)->toBe('cancelled')
        ->and($meetings[1]->fresh()->cancelled_by)->toBe($teacher->id)
        ->and($meetings[2]->fresh()->status)->toBe('scheduled')
        ->and($student->notifications()->count())->toBe(3);
    $cancellationNotice = $student->notifications()->get()->first(fn ($notification): bool => $notification->data['title'] === 'Class meeting cancelled');
    expect($cancellationNotice)->not->toBeNull()
        ->and($cancellationNotice->data['url'])->toBe(route('classes.meetings.show', [$schoolClass, $meetings[1]], false));
    $this->post(route('classes.meetings.cancel', [$schoolClass, $meetings[1]]))->assertForbidden();
    expect($student->notifications()->count())->toBe(3);
    $this->actingAs($student)->get(route('classes.meetings.index', $schoolClass))->assertOk()->assertSee('Cancelled');
    $this->actingAs($student)->get(route('classes.meetings.show', [$schoolClass, $meetings[1]]))->assertOk()->assertSee('Cancelled');
    $calendar = app(CalendarEvents::class)->for($student, $start->startOfMonth());
    $nextMonth = app(CalendarEvents::class)->for($student, $start->startOfMonth()->addMonth());
    $events = $calendar['events']->concat($nextMonth['events']);
    expect($events->pluck('id'))->toContain('meeting:'.$meetings[0]->id, 'meeting:'.$meetings[2]->id)
        ->not->toContain('meeting:'.$meetings[1]->id)
        ->and($events->firstWhere('id', 'meeting:'.$meetings[0]->id)['at']->toDateTimeString())->toBe($newStart->toDateTimeString());
    $this->actingAs($teacher)->patch(route('classes.meetings.update', [$schoolClass, $meetings[1]]), $updated)->assertForbidden();
});

test('meeting notifications reach only members who can currently access the class', function (): void {
    $teacher = meetingUser('teacher');
    $coTeacher = meetingUser('teacher');
    $activeStudent = meetingUser('student');
    $leavingStudent = meetingUser('student');
    $inactiveStudent = meetingUser('student');
    $newStudent = meetingUser('student');
    $outsider = meetingUser('teacher');
    $schoolClass = meetingClass($teacher, $activeStudent, $leavingStudent, $inactiveStudent);
    $schoolClass->members()->attach($coTeacher, ['role' => 'teacher', 'joined_at' => now()]);
    $inactiveStudent->update(['status' => 'inactive']);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();

    $this->actingAs($teacher)->post(route('classes.meetings.store', $schoolClass), meetingForm($start))->assertRedirect();
    $meeting = $schoolClass->meetings()->sole();
    expect($activeStudent->notifications()->count())->toBe(1)
        ->and($leavingStudent->notifications()->count())->toBe(1)
        ->and($coTeacher->notifications()->count())->toBe(1)
        ->and($inactiveStudent->notifications()->count())->toBe(0)
        ->and($newStudent->notifications()->count())->toBe(0)
        ->and($outsider->notifications()->count())->toBe(0)
        ->and($teacher->notifications()->count())->toBe(0);

    $schoolClass->memberRecords()->where('user_id', $leavingStudent->id)->delete();
    $schoolClass->members()->attach($newStudent, ['role' => 'student', 'joined_at' => now()]);
    $updated = meetingForm($start);
    $updated['description'] = 'Updated chapter review';
    unset($updated['recurrence'], $updated['occurrence_count']);
    $this->patch(route('classes.meetings.update', [$schoolClass, $meeting]), $updated)->assertRedirect();
    expect($activeStudent->notifications()->count())->toBe(2)
        ->and($leavingStudent->notifications()->count())->toBe(1)
        ->and($coTeacher->notifications()->count())->toBe(2)
        ->and($inactiveStudent->notifications()->count())->toBe(0)
        ->and($newStudent->notifications()->count())->toBe(1);
    expect($activeStudent->notifications()->get()->map(fn ($notification): string => $notification->data['title'])->all())
        ->toContain('Class meeting updated');

    $activeStudent->update(['status' => 'suspended']);
    $this->post(route('classes.meetings.cancel', [$schoolClass, $meeting]))->assertRedirect();
    expect($activeStudent->notifications()->count())->toBe(2)
        ->and($leavingStudent->notifications()->count())->toBe(1)
        ->and($coTeacher->notifications()->count())->toBe(3)
        ->and($inactiveStudent->notifications()->count())->toBe(0)
        ->and($newStudent->notifications()->count())->toBe(2)
        ->and($teacher->notifications()->count())->toBe(0);

    $this->actingAs($outsider)->post(route('classes.meetings.store', $schoolClass), meetingForm($start))->assertForbidden();
    $this->patch(route('classes.meetings.update', [$schoolClass, $meeting]), $updated)->assertForbidden();
    $this->post(route('classes.meetings.cancel', [$schoolClass, $meeting]))->assertForbidden();
    expect($newStudent->notifications()->count())->toBe(2);
});

test('meeting management and viewing follow current class membership', function (): void {
    $teacher = meetingUser('teacher');
    $student = meetingUser('student');
    $outsider = meetingUser('student');
    $admin = meetingUser('admin');
    $otherTeacher = meetingUser('teacher');
    $schoolClass = meetingClass($teacher, $student);
    $otherClass = meetingClass($otherTeacher);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();
    $meeting = ClassMeeting::factory()->create([
        'school_class_id' => $schoolClass->id, 'created_by' => $teacher->id,
        'starts_at' => $start, 'ends_at' => $start->addHour(), 'original_starts_at' => $start,
    ]);
    $this->actingAs($student)->get(route('classes.meetings.index', $schoolClass))->assertOk();
    $this->get(route('classes.meetings.create', $schoolClass))->assertForbidden();
    $this->post(route('classes.meetings.store', $schoolClass), meetingForm($start))->assertForbidden();
    $this->patch(route('classes.meetings.update', [$schoolClass, $meeting]), meetingForm($start->addDay()))->assertForbidden();
    $this->post(route('classes.meetings.cancel', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($outsider)->get(route('classes.meetings.index', $schoolClass))->assertForbidden();
    $this->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertForbidden();
    $this->actingAs($admin)->get(route('classes.show', $schoolClass))->assertOk();
    $this->get(route('classes.meetings.index', $schoolClass))->assertForbidden();
    $this->actingAs($teacher)->get(route('classes.meetings.show', [$otherClass, $meeting]))->assertNotFound();
    $schoolClass->memberRecords()->where('user_id', $student->id)->delete();
    $this->actingAs($student)->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertForbidden();
    $calendar = app(CalendarEvents::class)->for($student, $start->startOfMonth());
    expect($calendar['events']->pluck('id'))->not->toContain('meeting:'.$meeting->id);
    $schoolClass->forceFill(['archived_at' => now()])->save();
    $this->actingAs($teacher)->get(route('classes.meetings.show', [$schoolClass, $meeting]))->assertOk();
    $this->get(route('classes.meetings.create', $schoolClass))->assertForbidden();
    $this->post(route('classes.meetings.cancel', [$schoolClass, $meeting]))->assertForbidden();
});

test('meeting validation rejects invalid recurrence and duration without partial series', function (): void {
    $teacher = meetingUser('teacher');
    $schoolClass = meetingClass($teacher);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();
    $this->actingAs($teacher)->post(route('classes.meetings.store', $schoolClass), meetingForm($start, 'weekly', 1))
        ->assertSessionHasErrors('occurrence_count');
    $this->post(route('classes.meetings.store', $schoolClass), meetingForm($start, 'none', 3))
        ->assertSessionHasErrors('occurrence_count');
    $long = meetingForm($start);
    $long['ends_at'] = $start->addHours(9)->format('Y-m-d\TH:i');
    $this->post(route('classes.meetings.store', $schoolClass), $long)->assertSessionHasErrors('ends_at');
    $this->post(route('classes.meetings.store', $schoolClass), meetingForm($start, 'daily', 27))
        ->assertSessionHasErrors('occurrence_count');
    expect($schoolClass->meetings()->count())->toBe(0);
});

test('the example meeting seeder creates a bounded series once', function (): void {
    $teacher = meetingUser('teacher');
    $schoolClass = meetingClass($teacher);
    $this->seed(ClassMeetingSeeder::class);
    $this->seed(ClassMeetingSeeder::class);
    expect($schoolClass->meetings()->count())->toBe(3);
});

test('calendar caps meeting occurrences after class authorization', function (): void {
    $teacher = meetingUser('teacher');
    $student = meetingUser('student');
    $otherTeacher = meetingUser('teacher');
    $schoolClass = meetingClass($teacher, $student);
    $otherClass = meetingClass($otherTeacher);
    $start = CarbonImmutable::now(config('app.timezone'))->startOfMonth()->addMonth()->addDays(2)->setTime(9, 0);
    $rows = [];
    for ($number = 1; $number <= 252; $number++) {
        $at = $start->addMinutes($number);
        $rows[] = [
            'school_class_id' => $number === 252 ? $otherClass->id : $schoolClass->id,
            'created_by' => $number === 252 ? $otherTeacher->id : $teacher->id,
            'series_key' => (string) Str::uuid(),
            'title' => 'Meeting '.$number,
            'recurrence' => 'none', 'occurrence_number' => 1, 'occurrence_count' => 1,
            'original_starts_at' => $at, 'starts_at' => $at, 'ends_at' => $at->addMinutes(30),
            'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
        ];
    }
    ClassMeeting::query()->insert($rows);

    $calendar = app(CalendarEvents::class)->for($student, $start->startOfMonth());
    expect($calendar['events'])->toHaveCount(250)
        ->and($calendar['limited'])->toBeTrue()
        ->and($calendar['events']->pluck('title'))->not->toContain('Meeting 252');
});
