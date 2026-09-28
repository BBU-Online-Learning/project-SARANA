<?php

use App\Models\ClassAttendanceCorrection;
use App\Models\ClassAttendanceRegister;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\StudentClassEnrollment;
use App\Models\User;
use App\Services\ClassManagementService;
use Database\Seeders\ClassAttendanceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function attendanceUser(string $roleName, ?string $name = null): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id, 'name' => $name ?? fake()->name()]);
}

function attendanceClass(User $teacher): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()->subDays(10)]);

    return $schoolClass;
}

function addAttendanceStudent(User $teacher, SchoolClass $schoolClass, User $student): void
{
    app(ClassManagementService::class)->add($teacher, $schoolClass, $student->id, 'student');
}

test('opening captures dated enrollment history once and leaves every status unmarked', function () {
    $teacher = attendanceUser('teacher');
    $current = attendanceUser('student');
    $former = attendanceUser('student');
    $future = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $current);
    addAttendanceStudent($teacher, $schoolClass, $former);
    addAttendanceStudent($teacher, $schoolClass, $future);
    $day = now()->subDays(2)->toDateString();
    StudentClassEnrollment::query()->where('user_id', $current->id)->update(['started_at' => now()->subDays(5)]);
    StudentClassEnrollment::query()->where('user_id', $former->id)->update(['started_at' => now()->subDays(5), 'ended_at' => now()->subDay(), 'active_slot' => null]);
    StudentClassEnrollment::query()->where('user_id', $future->id)->update(['started_at' => now()->addDay()]);

    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => $day])->assertRedirect();
    $register = ClassAttendanceRegister::query()->sole();
    expect($register->records()->pluck('student_id')->sort()->values()->all())->toBe(collect([$current->id, $former->id])->sort()->values()->all())
        ->and($register->records()->whereNull('status')->count())->toBe(2);

    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => $day])->assertRedirect();
    expect(ClassAttendanceRegister::query()->count())->toBe(1)->and($register->records()->count())->toBe(2);
});

test('a roster needs complete bulk entry then explicit review before finalization', function () {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $student);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()]);
    $register = ClassAttendanceRegister::query()->sole();
    $record = $register->records()->sole();

    $this->actingAs($student)->get(route('classes.attendance.show', [$schoolClass, $register]))->assertForbidden();
    $this->actingAs($teacher)->post(route('classes.attendance.finalize', [$schoolClass, $register]))->assertSessionHasErrors('review');
    $this->actingAs($teacher)->post(route('classes.attendance.review', [$schoolClass, $register]), ['confirm_review' => '1'])->assertSessionHasErrors('review');
    $this->actingAs($teacher)->patch(route('classes.attendance.bulk', [$schoolClass, $register]), ['entries' => [$record->id => ['status' => 'absent', 'note' => 'Sick']]])->assertSessionHasNoErrors();
    $this->actingAs($teacher)->post(route('classes.attendance.finalize', [$schoolClass, $register]))->assertSessionHasErrors('review');
    $this->actingAs($teacher)->post(route('classes.attendance.review', [$schoolClass, $register]), ['confirm_review' => '1'])->assertSessionHasNoErrors();
    expect($register->fresh()->reviewed_at)->not->toBeNull();

    $this->actingAs($teacher)->patch(route('classes.attendance.bulk', [$schoolClass, $register]), ['entries' => [$record->id => ['status' => 'late']]])->assertSessionHasNoErrors();
    expect($register->fresh()->reviewed_at)->toBeNull();
    $this->actingAs($teacher)->post(route('classes.attendance.review', [$schoolClass, $register]), ['confirm_review' => '1'])->assertSessionHasNoErrors();
    $this->actingAs($teacher)->post(route('classes.attendance.finalize', [$schoolClass, $register]))->assertSessionHasNoErrors();
    expect($register->fresh()->finalized_at)->not->toBeNull();
    $this->actingAs($teacher)->patch(route('classes.attendance.bulk', [$schoolClass, $register]), ['entries' => [$record->id => ['status' => 'present']]])->assertForbidden();
    $this->actingAs($student)->get(route('classes.attendance.show', [$schoolClass, $register]))->assertOk()
        ->assertSee('Your attendance')->assertSee('Late')->assertDontSee('students marked');
});

test('a teacher can save a partial roster without marking other students or bypassing review', function () {
    $teacher = attendanceUser('teacher');
    $firstStudent = attendanceUser('student');
    $secondStudent = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $firstStudent);
    addAttendanceStudent($teacher, $schoolClass, $secondStudent);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()])->assertRedirect();
    $register = $schoolClass->attendanceRegisters()->sole();
    $firstRecord = $register->records()->where('student_id', $firstStudent->id)->sole();
    $secondRecord = $register->records()->where('student_id', $secondStudent->id)->sole();
    $bulkUrl = route('classes.attendance.bulk', [$schoolClass, $register]);

    $this->actingAs($teacher)->get(route('classes.attendance.show', [$schoolClass, $register]))
        ->assertOk()->assertSee('You can save a partly completed roster.')
        ->assertSee('0 of 2 students marked')
        ->assertSee('aria-label="Attendance steps"', false)
        ->assertSee('class="table attendance-roster"', false);
    $this->actingAs($secondStudent)->patch($bulkUrl, ['entries' => [$firstRecord->id => ['status' => 'present']]])->assertForbidden();
    $this->actingAs($teacher)->patch($bulkUrl, ['entries' => [
        $firstRecord->id => ['status' => 'present', 'note' => 'In class'],
        $secondRecord->id => ['status' => '', 'note' => ''],
    ]])->assertSessionHasNoErrors();

    expect($firstRecord->fresh()->status)->toBe('present')
        ->and($firstRecord->fresh()->marked_by)->toBe($teacher->id)
        ->and($secondRecord->fresh()->status)->toBeNull()
        ->and($secondRecord->fresh()->marked_at)->toBeNull()
        ->and($secondRecord->fresh()->marked_by)->toBeNull();
    $this->actingAs($teacher)->get(route('classes.attendance.show', [$schoolClass, $register]))
        ->assertOk()->assertSee('1 of 2 students marked')
        ->assertSee('Save a status for every student before review.');
    $this->actingAs($teacher)->post(route('classes.attendance.review', [$schoolClass, $register]), ['confirm_review' => '1'])->assertSessionHasErrors('review');
    $this->actingAs($teacher)->post(route('classes.attendance.finalize', [$schoolClass, $register]))->assertSessionHasErrors('review');

    $this->actingAs($teacher)->patch($bulkUrl, ['entries' => [$secondRecord->id => ['status' => 'late']]])->assertSessionHasNoErrors();
    $this->actingAs($teacher)->post(route('classes.attendance.review', [$schoolClass, $register]), ['confirm_review' => '1'])->assertSessionHasNoErrors();
    expect($register->fresh()->reviewed_at)->not->toBeNull();

    $this->actingAs($teacher)->patch($bulkUrl, ['entries' => [
        $firstRecord->id => ['status' => 'present', 'note' => 'In class'],
        $secondRecord->id => ['status' => 'late'],
    ]])->assertSessionHasNoErrors();
    expect($register->fresh()->reviewed_at)->not->toBeNull();

    $this->actingAs($teacher)->patch($bulkUrl, ['entries' => [$secondRecord->id => ['status' => '']]])->assertSessionHasNoErrors();
    expect($secondRecord->fresh()->status)->toBeNull()
        ->and($secondRecord->fresh()->marked_at)->toBeNull()
        ->and($secondRecord->fresh()->marked_by)->toBeNull()
        ->and($register->fresh()->reviewed_at)->toBeNull();
    $this->actingAs($teacher)->post(route('classes.attendance.finalize', [$schoolClass, $register]))->assertSessionHasErrors('review');

    $this->actingAs($teacher)->patch($bulkUrl, ['entries' => [$secondRecord->id => ['status' => 'excused']]])->assertSessionHasNoErrors();
    $this->actingAs($teacher)->post(route('classes.attendance.review', [$schoolClass, $register]), ['confirm_review' => '1'])->assertSessionHasNoErrors();
    $this->actingAs($teacher)->post(route('classes.attendance.finalize', [$schoolClass, $register]))->assertSessionHasNoErrors();
    expect($register->fresh()->finalized_at)->not->toBeNull();
});

test('finalized corrections require a reason and retain history visible to the student', function () {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $student);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()]);
    $register = ClassAttendanceRegister::query()->sole();
    $record = $register->records()->sole();
    $record->update(['status' => 'absent']);
    $register->update(['reviewed_at' => now(), 'reviewed_by' => $teacher->id, 'finalized_at' => now(), 'finalized_by' => $teacher->id]);

    $url = route('classes.attendance.correct', [$schoolClass, $register, $record]);
    $this->actingAs($teacher)->post($url, ['status' => 'excused', 'reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($teacher)->post($url, ['status' => 'excused', 'note' => 'Medical note', 'reason' => 'Medical note received'])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('excused')
        ->and(ClassAttendanceCorrection::query()->sole()->previous_status)->toBe('absent')
        ->and(ClassAttendanceCorrection::query()->sole()->reason)->toBe('Medical note received');
    $this->actingAs($teacher)->post($url, ['status' => 'late', 'reason' => 'Arrival time verified'])->assertSessionHasNoErrors();
    expect(ClassAttendanceCorrection::query()->orderBy('id')->get()->pluck('previous_status')->all())->toBe(['absent', 'excused']);
    $this->actingAs($student)->get(route('classes.attendance.show', [$schoolClass, $register]))->assertOk()->assertSee('Medical note received');
    $export = $this->actingAs($teacher)->get(route('classes.attendance.export', $schoolClass));
    ob_start();
    $export->baseResponse->sendContent();
    expect(ob_get_clean())->toContain('Medical note received')->toContain('Arrival time verified');
    app(ClassManagementService::class)->remove($teacher, $schoolClass, $student);
    $this->actingAs($student)->get(route('classes.attendance.show', [$schoolClass, $register]))->assertOk();
    $this->actingAs($student)->get(route('attendance.mine'))->assertOk()->assertSee($schoolClass->name)
        ->assertSee('class="table attendance-stack-table"', false)
        ->assertSee('class="class-status class-status--warning"', false);
});

test('reports and exports are restricted and nested records cannot cross classes', function () {
    $teacher = attendanceUser('teacher');
    $otherTeacher = attendanceUser('teacher');
    $student = attendanceUser('student', '=SUM(1+1)');
    $schoolClass = attendanceClass($teacher);
    $otherClass = attendanceClass($otherTeacher);
    addAttendanceStudent($teacher, $schoolClass, $student);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()]);
    $register = ClassAttendanceRegister::query()->sole();
    $record = $register->records()->sole();
    $record->update(['status' => 'late', 'note' => '=HYPERLINK("bad")']);
    $register->update(['finalized_at' => now(), 'finalized_by' => $teacher->id]);

    $this->actingAs($student)->get(route('classes.attendance.report', $schoolClass))->assertForbidden();
    $this->actingAs($student)->get(route('classes.attendance.export', $schoolClass))->assertForbidden();
    $this->actingAs($otherTeacher)->get(route('classes.attendance.report', $schoolClass))->assertForbidden();
    $this->actingAs($teacher)->get(route('classes.attendance.show', [$otherClass, $register]))->assertNotFound();
    $this->actingAs($teacher)->get(route('classes.attendance.report', $schoolClass))->assertOk()
        ->assertSee($register->attendance_date->toDateString())
        ->assertSee('class="table attendance-stack-table"', false)
        ->assertSee('class="class-status class-status--success"', false);
    $response = $this->actingAs($teacher)->get(route('classes.attendance.export', $schoolClass));
    $response->assertOk();
    ob_start();
    $response->baseResponse->sendContent();
    $csv = ob_get_clean();
    expect($csv)->toContain("'=SUM(1+1)")->toContain("'=HYPERLINK");
});

test('roster snapshot does not change when class membership changes later', function () {
    $teacher = attendanceUser('teacher');
    $first = attendanceUser('student');
    $second = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $first);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()])->assertRedirect();
    $register = ClassAttendanceRegister::query()->sole();
    app(ClassManagementService::class)->remove($teacher, $schoolClass, $first);
    addAttendanceStudent($teacher, $schoolClass, $second);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()])->assertRedirect();
    expect($register->records()->pluck('student_id')->all())->toBe([$first->id]);
});

test('cross class records cannot be entered or corrected and archived classes are read only', function () {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    $otherClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $student);
    addAttendanceStudent($teacher, $otherClass, $student);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()]);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $otherClass), ['attendance_date' => now()->toDateString()]);
    $register = $schoolClass->attendanceRegisters()->sole();
    $otherRecord = $otherClass->attendanceRegisters()->sole()->records()->sole();

    $this->actingAs($teacher)->patch(route('classes.attendance.bulk', [$schoolClass, $register]), ['entries' => [$otherRecord->id => ['status' => 'present']]])->assertSessionHasErrors('entries');
    $register->update(['finalized_at' => now(), 'finalized_by' => $teacher->id]);
    $this->actingAs($teacher)->post(route('classes.attendance.correct', [$schoolClass, $register, $otherRecord]), ['status' => 'absent', 'reason' => 'Wrong class'])->assertNotFound();
    $schoolClass->forceFill(['archived_at' => now()])->save();
    $this->actingAs($teacher)->get(route('classes.attendance.report', $schoolClass))->assertOk();
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->subDay()->toDateString()])->assertForbidden();
});

test('student index shows only finalized registers containing their own snapshot', function () {
    $teacher = attendanceUser('teacher');
    $first = attendanceUser('student');
    $second = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $first);
    StudentClassEnrollment::query()->where('user_id', $first->id)->update(['started_at' => now()->subDays(2)]);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->subDay()->toDateString()]);
    $firstRegister = ClassAttendanceRegister::query()->sole();
    $firstRegister->records()->update(['status' => 'present']);
    $firstRegister->update(['finalized_at' => now(), 'finalized_by' => $teacher->id]);
    addAttendanceStudent($teacher, $schoolClass, $second);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()]);
    $secondRegister = $schoolClass->attendanceRegisters()->whereDate('attendance_date', now()->toDateString())->sole();

    $this->actingAs($first)->get(route('classes.attendance.index', $schoolClass))->assertOk()->assertSee($firstRegister->attendance_date->format('d M Y'));
    $this->actingAs($second)->get(route('classes.attendance.index', $schoolClass))->assertOk()->assertDontSee($firstRegister->attendance_date->format('d M Y'));
    $this->actingAs($second)->get(route('classes.attendance.show', [$schoolClass, $firstRegister]))->assertForbidden();
    $this->actingAs($first)->get(route('classes.attendance.show', [$schoolClass, $secondRegister]))->assertForbidden();
});

test('demo seeder leaves its dated register unmarked', function () {
    $teacher = attendanceUser('teacher');
    $student = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $student);

    $this->seed(ClassAttendanceDemoSeeder::class);
    $this->seed(ClassAttendanceDemoSeeder::class);

    expect($schoolClass->attendanceRegisters()->count())->toBe(1)
        ->and($schoolClass->attendanceRegisters()->sole()->records()->sole()->status)->toBeNull();
});

test('administrators can report but cannot enter or correct class attendance', function () {
    $teacher = attendanceUser('teacher');
    $admin = attendanceUser('admin');
    $student = attendanceUser('student');
    $schoolClass = attendanceClass($teacher);
    addAttendanceStudent($teacher, $schoolClass, $student);
    $this->actingAs($teacher)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->toDateString()]);
    $register = $schoolClass->attendanceRegisters()->sole();
    $record = $register->records()->sole();
    $record->update(['status' => 'present']);
    $register->update(['finalized_at' => now(), 'finalized_by' => $teacher->id]);

    $this->actingAs($admin)->get(route('classes.attendance.report', $schoolClass))->assertOk();
    $this->actingAs($admin)->get(route('classes.attendance.export', $schoolClass))->assertOk();
    $this->actingAs($admin)->post(route('classes.attendance.open', $schoolClass), ['attendance_date' => now()->subDay()->toDateString()])->assertForbidden();
    $this->actingAs($admin)->post(route('classes.attendance.correct', [$schoolClass, $register, $record]), ['status' => 'absent', 'reason' => 'Reviewed note'])->assertForbidden();
});
