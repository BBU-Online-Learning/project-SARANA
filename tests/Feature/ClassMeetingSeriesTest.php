<?php

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassMeetingSeriesService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seriesActor(string $role): User
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role], ['description' => $role, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $roleModel->id]);
}

function seriesClass(User $teacher, User $student): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);

    return $schoolClass;
}

/** @return array<string, mixed> */
function ongoingMeetingForm(CarbonImmutable $start): array
{
    return [
        'title' => 'Ongoing science lab', 'description' => 'Weekly lab',
        'starts_at' => $start->format('Y-m-d\TH:i'),
        'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
        'recurrence' => 'weekly', 'ongoing' => '1',
    ];
}

test('ongoing series replenishes without replacing changed occurrences', function (): void {
    $teacher = seriesActor('teacher');
    $student = seriesActor('student');
    $schoolClass = seriesClass($teacher, $student);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();

    $this->actingAs($teacher)->post(route('classes.meetings.store', $schoolClass), ongoingMeetingForm($start))
        ->assertRedirect()->assertSessionHasNoErrors();
    $series = $schoolClass->meetingSeries()->sole();
    $first = $series->occurrences()->orderBy('starts_at')->firstOrFail();
    expect($series->occurrences()->count())->toBeGreaterThan(2)
        ->and($student->notifications()->count())->toBe(1);

    $this->post(route('classes.meetings.cancel', [$schoolClass, $first]))->assertRedirect();
    $this->get(route('classes.meetings.show', [$schoolClass, $first]))->assertOk()->assertSee('Cancel future meetings in this series');
    $count = $series->occurrences()->count();
    $service = app(ClassMeetingSeriesService::class);
    expect($service->replenish($series, CarbonImmutable::today(config('app.timezone'))->addDays(90)))->toBeGreaterThan(0)
        ->and($service->replenish($series, CarbonImmutable::today(config('app.timezone'))->addDays(90)))->toBe(0)
        ->and($first->fresh()->status)->toBe('cancelled')
        ->and($series->occurrences()->count())->toBeGreaterThan($count);
    $this->actingAs($student)->get(route('classes.meetings.show', [$schoolClass, $first]))->assertOk()->assertSee('Ongoing weekly series');
});

test('selected weekdays and series cancellation keep current class permissions', function (): void {
    $teacher = seriesActor('teacher');
    $student = seriesActor('student');
    $outsider = seriesActor('student');
    $schoolClass = seriesClass($teacher, $student);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();
    $form = ongoingMeetingForm($start);
    $form['recurrence'] = 'selected_weekdays';
    $form['weekdays'] = [1, 3];

    $this->actingAs($outsider)->post(route('classes.meetings.store', $schoolClass), $form)->assertForbidden();
    $this->actingAs($teacher)->post(route('classes.meetings.store', $schoolClass), $form)->assertRedirect()->assertSessionHasNoErrors();
    $series = $schoolClass->meetingSeries()->sole();
    expect($series->occurrences()->count())->toBeGreaterThan(4);
    foreach ($series->occurrences as $meeting) {
        expect($meeting->starts_at->dayOfWeekIso)->toBeIn([1, 3]);
    }
    $this->actingAs($student)->post(route('classes.meetings.series.cancel', [$schoolClass, $series]))->assertForbidden();
    $this->actingAs($teacher)->post(route('classes.meetings.series.cancel', [$schoolClass, $series]))->assertRedirect();
    expect($series->fresh()->status)->toBe('cancelled')
        ->and($series->occurrences()->where('status', 'scheduled')->count())->toBe(0)
        ->and($student->notifications()->count())->toBe(2);
    $this->artisan('class-meetings:replenish')->assertSuccessful();
    expect($series->occurrences()->where('status', 'scheduled')->count())->toBe(0);
});

test('an ongoing series cannot end before its first selected weekday', function (): void {
    $teacher = seriesActor('teacher');
    $student = seriesActor('student');
    $schoolClass = seriesClass($teacher, $student);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();
    $form = ongoingMeetingForm($start);
    $form['recurrence'] = 'selected_weekdays';
    $form['weekdays'] = [$start->addDay()->dayOfWeekIso];
    $form['repeat_until'] = $start->toDateString();

    $this->actingAs($teacher)->post(route('classes.meetings.store', $schoolClass), $form)
        ->assertSessionHasErrors('weekdays');
    expect($schoolClass->meetingSeries()->count())->toBe(0);
});

test('a series with a repeat end date completes after that date', function (): void {
    $teacher = seriesActor('teacher');
    $student = seriesActor('student');
    $schoolClass = seriesClass($teacher, $student);
    $start = CarbonImmutable::now(config('app.timezone'))->addDays(3)->startOfHour();
    $form = ongoingMeetingForm($start);
    $form['recurrence'] = 'daily';
    $form['repeat_until'] = $start->toDateString();

    $this->actingAs($teacher)->post(route('classes.meetings.store', $schoolClass), $form)->assertRedirect();
    $series = $schoolClass->meetingSeries()->sole();
    expect($series->occurrences()->count())->toBe(1);
    $this->travelTo($start->addDay());
    $this->artisan('class-meetings:replenish')->assertSuccessful();
    expect($series->fresh()->status)->toBe('completed');
});
