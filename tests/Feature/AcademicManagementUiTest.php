<?php

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

function academicManagementUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function academicManagementClass(User $teacher, string $name, ?AcademicYear $year = null, bool $archived = false): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create([
        'created_by' => $teacher->id,
        'name' => $name,
        'academic_year_id' => $year?->id,
        'archived_at' => $archived ? now() : null,
    ]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);

    return $schoolClass;
}

test('academic class list filters by search year and archive status', function (): void {
    $admin = academicManagementUser('super_admin');
    $teacher = academicManagementUser('teacher');
    $firstYear = AcademicYear::factory()->create(['name' => 'Year A']);
    $secondYear = AcademicYear::factory()->create(['name' => 'Year B']);
    $activeBiology = academicManagementClass($teacher, 'Biology A', $firstYear);
    $archivedBiology = academicManagementClass($teacher, 'Biology B', $firstYear, true);
    academicManagementClass($teacher, 'Physics A', $secondYear);

    $this->actingAs($admin)->get(route('academics.index', [
        'search' => '  Biology  ', 'academic_year_id' => $firstYear->id, 'status' => 'active',
    ]))->assertOk()
        ->assertViewHas('classes', fn (LengthAwarePaginator $classes): bool => $classes->total() === 1
            && $classes->getCollection()->first()->is($activeBiology))
        ->assertSee('value="Biology"', false)
        ->assertSee(route('academics.classes.edit', $activeBiology), false)
        ->assertDontSee(route('academics.classes.edit', $archivedBiology), false)
        ->assertDontSee('action="'.route('academics.classes.update', $activeBiology).'"', false);

    $this->get(route('academics.index', ['search' => $archivedBiology->join_code, 'status' => 'archived']))
        ->assertOk()->assertViewHas('classes', fn (LengthAwarePaginator $classes): bool => $classes->total() === 1
            && $classes->getCollection()->first()->is($archivedBiology));
    $this->get(route('academics.index', ['academic_year_id' => $secondYear->id]))
        ->assertOk()->assertViewHas('classes', fn (LengthAwarePaginator $classes): bool => $classes->total() === 1);
});

test('academic class list paginates with stable order and keeps filters in page links', function (): void {
    $admin = academicManagementUser('super_admin');
    $teacher = academicManagementUser('teacher');
    $year = AcademicYear::factory()->create();
    for ($number = 1; $number <= 17; $number++) {
        academicManagementClass($teacher, sprintf('Room %02d', $number), $year);
    }
    academicManagementClass($teacher, 'Other class');
    $filters = ['search' => 'Room', 'academic_year_id' => $year->id, 'status' => 'active'];

    $this->actingAs($admin)->get(route('academics.index', $filters))->assertOk()
        ->assertViewHas('classes', fn (LengthAwarePaginator $classes): bool => $classes->total() === 17
            && $classes->count() === 15
            && $classes->getCollection()->first()->name === 'Room 01'
            && $classes->getCollection()->last()->name === 'Room 15'
            && str_contains($classes->nextPageUrl(), 'search=Room')
            && str_contains($classes->nextPageUrl(), 'academic_year_id='.$year->id))
        ->assertSee('Room 15')->assertDontSee('Room 16');
    $this->get(route('academics.index', $filters + ['page' => 2]))->assertOk()
        ->assertViewHas('classes', fn (LengthAwarePaginator $classes): bool => $classes->total() === 17
            && $classes->count() === 2
            && $classes->getCollection()->pluck('name')->all() === ['Room 16', 'Room 17'])
        ->assertSee('Room 16')->assertSee('Room 17')->assertDontSee('Room 15');
});

test('only administrators can use the focused edit flow and it preserves class identity and membership', function (): void {
    $admin = academicManagementUser('super_admin');
    $teacher = academicManagementUser('teacher');
    $student = academicManagementUser('student');
    $firstYear = AcademicYear::factory()->create(['name' => 'Initial year']);
    $nextYear = AcademicYear::factory()->create(['name' => 'Next year']);
    $grade = GradeLevel::factory()->create();
    $subject = Subject::factory()->create();
    $schoolClass = academicManagementClass($teacher, 'Focused class', $firstYear);
    $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    $originalId = $schoolClass->id;
    $originalJoinCode = $schoolClass->join_code;

    $this->actingAs($teacher)->get(route('academics.index'))->assertForbidden();
    $this->get(route('academics.classes.edit', $schoolClass))->assertForbidden();
    $this->patch(route('academics.classes.update', $schoolClass), ['academic_year_id' => $nextYear->id])->assertForbidden();
    $this->actingAs($student)->get(route('academics.classes.edit', $schoolClass))->assertForbidden();

    $this->actingAs($admin)->get(route('academics.classes.edit', $schoolClass))->assertOk()
        ->assertSee('Focused class')->assertSee($originalJoinCode)
        ->assertSee('name="subject_ids[]"', false)
        ->assertSee(route('academics.classes.update', $schoolClass), false);
    $this->patch(route('academics.classes.update', $schoolClass), [
        'academic_year_id' => $nextYear->id,
        'grade_level_id' => $grade->id,
        'subject_ids' => [$subject->id],
    ])->assertRedirect(route('academics.classes.edit', $schoolClass))->assertSessionHasNoErrors();

    expect($schoolClass->fresh()->id)->toBe($originalId)
        ->and($schoolClass->fresh()->join_code)->toBe($originalJoinCode)
        ->and($schoolClass->fresh()->academic_year_id)->toBe($nextYear->id)
        ->and($schoolClass->fresh()->grade_level_id)->toBe($grade->id)
        ->and($schoolClass->fresh()->subjects->pluck('id')->all())->toBe([$subject->id])
        ->and($schoolClass->memberRecords()->count())->toBe(2);

    $this->patch(route('academics.classes.update', $schoolClass), [
        'academic_year_id' => $nextYear->id,
    ])->assertRedirect(route('academics.classes.edit', $schoolClass))->assertSessionHasNoErrors();
    expect($schoolClass->fresh()->subjects)->toBeEmpty()
        ->and($schoolClass->fresh()->grade_level_id)->toBeNull()
        ->and($schoolClass->memberRecords()->count())->toBe(2);
});
