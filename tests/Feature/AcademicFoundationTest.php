<?php

use App\Models\AcademicYear;
use App\Models\ClassMembershipAudit;
use App\Models\ClassSubject;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\StudentClassEnrollment;
use App\Models\Subject;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use App\Services\ClassManagementService;
use Database\Seeders\AcademicCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function academicUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function academicClass(User $owner): SchoolClass
{
    $schoolClass = SchoolClass::query()->create([
        'name' => 'Academic foundation class',
        'join_code' => 'ACAD'.fake()->unique()->numerify('####'),
        'created_by' => $owner->id,
    ]);
    $schoolClass->members()->attach($owner, ['role' => 'owner', 'joined_at' => now()->subDays(5)]);

    return $schoolClass;
}

test('backfill labels old classes and preserves membership and audit data', function () {
    $owner = academicUser('teacher');
    $student = academicUser('student');
    $schoolClass = academicClass($owner);
    $joinedAt = now()->subDays(3)->startOfSecond();
    $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => $joinedAt]);
    ClassMembershipAudit::query()->create([
        'school_class_id' => $schoolClass->id,
        'actor_id' => $owner->id,
        'target_id' => $student->id,
        'actor_role' => 'teacher',
        'action' => 'joined',
        'old_role' => null,
        'new_role' => 'student',
    ]);
    $originalId = $schoolClass->id;
    $originalCode = $schoolClass->join_code;

    $migration = require database_path('migrations/2026_09_27_185840_backfill_class_academic_history.php');
    $migration->up();
    $migration->up();

    expect($schoolClass->fresh()->id)->toBe($originalId)
        ->and($schoolClass->fresh()->join_code)->toBe($originalCode)
        ->and($schoolClass->fresh()->academicYear->is_legacy)->toBeTrue()
        ->and($schoolClass->memberRecords()->count())->toBe(2)
        ->and(ClassMembershipAudit::query()->count())->toBe(1)
        ->and(StudentClassEnrollment::query()->where('school_class_id', $originalId)->count())->toBe(1)
        ->and(TeacherClassAssignment::query()->where('school_class_id', $originalId)->count())->toBe(1);
    expect(StudentClassEnrollment::query()->firstOrFail()->started_at->toDateTimeString())->toBe($joinedAt->toDateTimeString());
    expect(StudentClassEnrollment::query()->firstOrFail()->academic_year_id)->toBe($schoolClass->fresh()->academic_year_id);
});

test('member changes preserve dated student history while current class access follows the original membership', function () {
    $owner = academicUser('teacher');
    $student = academicUser('student');
    $schoolClass = academicClass($owner);
    $classes = app(ClassManagementService::class);

    expect($classes->add($owner, $schoolClass, $student->id, 'student'))->toBeTrue();
    $this->actingAs($student)->get(route('classes.show', $schoolClass))->assertOk();
    expect($classes->remove($owner, $schoolClass, $student))->toBeNull();
    $this->actingAs($student)->get(route('classes.show', $schoolClass))->assertForbidden();
    expect($classes->add($owner, $schoolClass, $student->id, 'student'))->toBeTrue();

    $this->actingAs($student)->get(route('classes.show', $schoolClass))->assertDontSee('Recent assignment history');
    $this->actingAs($owner)->get(route('classes.show', $schoolClass))->assertSee('Recent assignment history');

    $periods = StudentClassEnrollment::query()->where('school_class_id', $schoolClass->id)->where('user_id', $student->id)->orderBy('id')->get();
    expect($periods)->toHaveCount(2)
        ->and($periods[0]->ended_at)->not->toBeNull()
        ->and($periods[0]->active_slot)->toBeNull()
        ->and($periods[1]->ended_at)->toBeNull()
        ->and($periods[1]->active_slot)->toBe(1)
        ->and($schoolClass->memberRecords()->where('user_id', $student->id)->count())->toBe(1)
        ->and(ClassMembershipAudit::query()->where('target_id', $student->id)->count())->toBe(3);
});

test('administrators can define academic context without changing the class identity', function () {
    $admin = academicUser('super_admin');
    $owner = academicUser('teacher');
    $schoolClass = academicClass($owner);
    $classId = $schoolClass->id;
    $joinCode = $schoolClass->join_code;

    $this->actingAs($admin)->post(route('academics.years.store'), [
        'name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'active',
    ])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('academics.grades.store'), ['name' => 'Grade 7', 'sequence' => 7])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('academics.subjects.store'), ['code' => 'MATH7', 'name' => 'Mathematics'])->assertSessionHasNoErrors();

    $year = AcademicYear::query()->where('name', '2026-2027')->firstOrFail();
    $grade = GradeLevel::query()->where('name', 'Grade 7')->firstOrFail();
    $subject = Subject::query()->where('code', 'MATH7')->firstOrFail();
    $this->actingAs($admin)->patch(route('academics.classes.update', $schoolClass), [
        'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'subject_ids' => [$subject->id],
    ])->assertSessionHasNoErrors();

    expect($schoolClass->fresh()->id)->toBe($classId)
        ->and($schoolClass->fresh()->join_code)->toBe($joinCode)
        ->and($schoolClass->fresh()->academicYear->id)->toBe($year->id)
        ->and($schoolClass->fresh()->gradeLevel->id)->toBe($grade->id)
        ->and($schoolClass->fresh()->subjects->pluck('id')->all())->toBe([$subject->id]);
    $this->actingAs($owner)->get(route('academics.index'))->assertForbidden();
});

test('academic year dates and unauthorized catalog changes are rejected', function () {
    $admin = academicUser('super_admin');
    $student = academicUser('student');

    $this->actingAs($admin)->post(route('academics.years.store'), [
        'name' => 'Invalid year', 'starts_on' => '2027-09-01', 'ends_on' => '2027-01-01', 'status' => 'active',
    ])->assertSessionHasErrors('ends_on');
    $this->actingAs($student)->post(route('academics.subjects.store'), ['code' => 'NOPE', 'name' => 'Forbidden'])->assertForbidden();
    expect(AcademicYear::query()->where('name', 'Invalid year')->exists())->toBeFalse();
});

test('changing a class academic year closes the prior assignment period without changing current membership', function () {
    $admin = academicUser('super_admin');
    $owner = academicUser('teacher');
    $schoolClass = academicClass($owner);
    app(ClassManagementService::class)->audit($owner, $schoolClass, $owner, 'owner_assigned', null, 'owner');
    $firstYear = AcademicYear::factory()->create(['name' => '2026-2027']);
    $nextYear = AcademicYear::factory()->create(['name' => '2027-2028']);

    foreach ([$firstYear, $nextYear] as $year) {
        $this->actingAs($admin)->patch(route('academics.classes.update', $schoolClass), [
            'academic_year_id' => $year->id,
        ])->assertSessionHasNoErrors();
        $schoolClass->refresh();
    }

    $periods = TeacherClassAssignment::query()->where('school_class_id', $schoolClass->id)->orderBy('id')->get();
    expect($periods)->toHaveCount(3)
        ->and($periods[0]->end_reason)->toBe('academic_year_changed')
        ->and($periods[1]->academic_year_id)->toBe($firstYear->id)
        ->and($periods[1]->ended_at)->not->toBeNull()
        ->and($periods[2]->academic_year_id)->toBe($nextYear->id)
        ->and($periods[2]->active_slot)->toBe(1)
        ->and($schoolClass->memberRecords()->where('user_id', $owner->id)->where('role', 'owner')->exists())->toBeTrue();
});

test('ownership transfer records both teacher role periods and the former owner student period', function () {
    $owner = academicUser('teacher');
    $successor = academicUser('teacher');
    $schoolClass = academicClass($owner);
    $classes = app(ClassManagementService::class);
    $classes->audit($owner, $schoolClass, $owner, 'owner_assigned', null, 'owner');
    expect($classes->add($owner, $schoolClass, $successor->id, 'teacher'))->toBeTrue();

    $classes->transfer($owner, $schoolClass, $successor->id);

    expect(TeacherClassAssignment::query()->where('school_class_id', $schoolClass->id)->where('user_id', $owner->id)->where('role', 'owner')->whereNull('active_slot')->count())->toBe(1)
        ->and(StudentClassEnrollment::query()->where('school_class_id', $schoolClass->id)->where('user_id', $owner->id)->where('active_slot', 1)->count())->toBe(1)
        ->and(TeacherClassAssignment::query()->where('school_class_id', $schoolClass->id)->where('user_id', $successor->id)->where('role', 'teacher')->whereNull('active_slot')->count())->toBe(1)
        ->and(TeacherClassAssignment::query()->where('school_class_id', $schoolClass->id)->where('user_id', $successor->id)->where('role', 'owner')->where('active_slot', 1)->count())->toBe(1)
        ->and($schoolClass->memberRecords()->where('role', 'owner')->value('user_id'))->toBe($successor->id);
});

test('demo academic catalog seeding is explicit and repeatable', function () {
    $this->seed(AcademicCatalogSeeder::class);
    $this->seed(AcademicCatalogSeeder::class);

    expect(AcademicYear::query()->where('name', 'Demo academic year')->count())->toBe(1)
        ->and(GradeLevel::query()->where('name', 'Demo level')->count())->toBe(1)
        ->and(Subject::query()->where('code', 'DEMO')->count())->toBe(1);
});

test('academic factories create related records for test scenarios', function () {
    $schoolClass = SchoolClass::factory()->create();
    $year = AcademicYear::factory()->create();
    $grade = GradeLevel::factory()->create();
    $subject = Subject::factory()->create();
    $link = ClassSubject::factory()->create(['school_class_id' => $schoolClass->id, 'subject_id' => $subject->id]);
    $studentPeriod = StudentClassEnrollment::factory()->create(['school_class_id' => $schoolClass->id, 'academic_year_id' => $year->id]);
    $teacherPeriod = TeacherClassAssignment::factory()->create(['school_class_id' => $schoolClass->id, 'academic_year_id' => $year->id]);

    expect($link->schoolClass->is($schoolClass))->toBeTrue()
        ->and($studentPeriod->academicYear->is($year))->toBeTrue()
        ->and($teacherPeriod->academicYear->is($year))->toBeTrue()
        ->and($grade->exists)->toBeTrue();
});
