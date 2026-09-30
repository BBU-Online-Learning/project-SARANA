<?php

use App\Models\AcademicYear;
use App\Models\CourseworkAssignment;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportingUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function reportingPayload(AcademicYear $year, array $overrides = []): array
{
    return array_replace([
        'academic_year_id' => $year->id,
        'parent_id' => null,
        'name' => 'Term 1',
        'code' => 'TERM-1',
        'sequence' => 1,
        'starts_on' => $year->starts_on->toDateString(),
        'ends_on' => $year->ends_on->toDateString(),
    ], $overrides);
}

test('administrators manage dated reporting periods and lifecycle through the React screen', function (): void {
    $admin = reportingUser('super_admin');
    $year = AcademicYear::factory()->create();

    $this->actingAs($admin)->get(route('academics.reporting-periods.index'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('ReportingPeriods/Index')->has('years', 1)->has('periods', 0));
    $this->post(route('academics.reporting-periods.store'), reportingPayload($year))
        ->assertRedirect(route('academics.reporting-periods.index'))->assertSessionHasNoErrors();
    $period = ReportingPeriod::query()->firstOrFail();
    expect($period->status)->toBe('draft')->and($year->reportingPeriods()->count())->toBe(1);

    $this->patch(route('academics.reporting-periods.update', $period), reportingPayload($year, ['name' => 'First term']))
        ->assertSessionHasNoErrors();
    expect($period->fresh()->name)->toBe('First term');
    $this->post(route('academics.reporting-periods.transition', $period), ['status' => 'closed'])
        ->assertSessionHasErrors('status');
    $this->post(route('academics.reporting-periods.transition', $period), ['status' => 'open'])
        ->assertSessionHasNoErrors();
    expect($period->fresh()->status)->toBe('open');
    $this->patch(route('academics.reporting-periods.update', $period), reportingPayload($year))
        ->assertSessionHasErrors('reporting_period');
    $this->post(route('academics.reporting-periods.transition', $period), ['status' => 'closed'])
        ->assertSessionHasNoErrors();
    expect($period->fresh()->status)->toBe('closed');
    $this->post(route('academics.reporting-periods.transition', $period), ['status' => 'open'])
        ->assertSessionHasErrors('status');
});

test('reporting periods reject duplicate codes and invalid year or parent boundaries', function (): void {
    $admin = reportingUser('admin');
    $year = AcademicYear::factory()->create();
    $otherYear = AcademicYear::factory()->create();
    $parent = ReportingPeriod::factory()->create(reportingPayload($year));

    $this->actingAs($admin)->post(route('academics.reporting-periods.store'), reportingPayload($year, ['name' => 'Duplicate']))
        ->assertSessionHasErrors('code');
    $this->post(route('academics.reporting-periods.store'), reportingPayload($year, ['name' => 'Wrong dates', 'code' => 'OUTSIDE', 'starts_on' => $year->starts_on->subDay()->toDateString()]))
        ->assertSessionHasErrors('starts_on');
    $this->post(route('academics.reporting-periods.store'), reportingPayload($otherYear, ['parent_id' => $parent->id, 'code' => 'CROSS-YEAR']))
        ->assertSessionHasErrors('parent_id');
    $this->post(route('academics.reporting-periods.store'), reportingPayload($year, [
        'parent_id' => $parent->id, 'name' => 'Child', 'code' => 'CHILD', 'sequence' => 2,
        'starts_on' => $year->starts_on->addMonth()->toDateString(),
        'ends_on' => $year->ends_on->subMonth()->toDateString(),
    ]))->assertSessionHasNoErrors();
    $child = ReportingPeriod::query()->where('code', 'CHILD')->firstOrFail();

    $this->patch(route('academics.reporting-periods.update', $parent), reportingPayload($year, ['parent_id' => $child->id]))
        ->assertSessionHasErrors('parent_id');
    $this->patch(route('academics.reporting-periods.update', $parent), reportingPayload($year, ['starts_on' => $child->starts_on->addDay()->toDateString()]))
        ->assertSessionHasErrors('starts_on');
    $this->patch(route('academics.reporting-periods.update', $parent), reportingPayload($otherYear, ['code' => 'OTHER']))
        ->assertSessionHasErrors('academic_year_id');
    expect($parent->fresh()->parent_id)->toBeNull();
});

test('only administrators can manage periods and coursework can use only its own academic year', function (): void {
    $admin = reportingUser('admin');
    $teacher = reportingUser('teacher');
    $student = reportingUser('student');
    $year = AcademicYear::factory()->create();
    $otherYear = AcademicYear::factory()->create();
    $period = ReportingPeriod::factory()->create(reportingPayload($year));
    $otherPeriod = ReportingPeriod::factory()->create(reportingPayload($otherYear));
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id, 'academic_year_id' => $year->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($teacher)->get(route('academics.reporting-periods.index'))->assertForbidden();
    $this->post(route('academics.reporting-periods.store'), reportingPayload($year, ['code' => 'NO']))->assertForbidden();
    $this->actingAs($student)->post(route('academics.reporting-periods.transition', $period), ['status' => 'open'])->assertForbidden();
    $this->actingAs($admin)->get(route('academics.index'))->assertSee('Manage reporting periods');

    $assignment = ['title' => 'Term assignment', 'max_points' => '20.00', 'allow_resubmissions' => 1];
    $this->actingAs($teacher)->get(route('classes.coursework.assignments.create', $schoolClass))
        ->assertOk()->assertSee('Reporting period (optional)')->assertSee('Term 1');
    $this->post(route('classes.coursework.assignments.store', $schoolClass), $assignment + ['reporting_period_id' => $otherPeriod->id])
        ->assertSessionHasErrors('reporting_period_id');
    $this->post(route('classes.coursework.assignments.store', $schoolClass), $assignment + ['reporting_period_id' => $period->id])
        ->assertSessionHasNoErrors();
    $saved = CourseworkAssignment::query()->firstOrFail();
    expect($saved->reportingPeriod->is($period))->toBeTrue();
    $this->get(route('classes.coursework.assignments.show', [$schoolClass, $saved]))->assertSee('Term 1');

    $schoolClass->update(['academic_year_id' => $otherYear->id]);
    $this->get(route('classes.coursework.assignments.edit', [$schoolClass, $saved]))->assertSee('Term 1');
    $this->patch(route('classes.coursework.assignments.update', [$schoolClass, $saved]), $assignment + ['reporting_period_id' => $period->id])
        ->assertSessionHasNoErrors();
    expect($saved->fresh()->reporting_period_id)->toBe($period->id);
});
