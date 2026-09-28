<?php

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function classroomUiUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function classroomUiClass(User $teacher, User $student): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id, 'name' => 'Biology 10A']);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    app(ClassManagementService::class)->add($teacher, $schoolClass, $student->id, 'student');

    return $schoolClass;
}

test('teacher class pages share navigation with the active section and permitted actions', function (): void {
    $teacher = classroomUiUser('teacher');
    $student = classroomUiUser('student');
    $schoolClass = classroomUiClass($teacher, $student);

    $this->actingAs($teacher)->get(route('classes.show', $schoolClass))->assertOk()
        ->assertSee('aria-label="Class sections"', false)
        ->assertSee('class="is-active" aria-current="page" >Overview', false)
        ->assertSee(route('classes.coursework.index', $schoolClass), false)
        ->assertSee(route('classes.attendance.index', $schoolClass), false)
        ->assertSee(route('classes.meetings.index', $schoolClass), false);

    $this->get(route('classes.coursework.index', $schoolClass))->assertOk()
        ->assertSee('class="is-active" aria-current="page" >Coursework', false)
        ->assertSee('Create assignment')
        ->assertSee('class="class-empty"', false)
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('Biology 10A');
    $this->get(route('classes.attendance.index', $schoolClass))->assertOk()
        ->assertSee('class="is-active" aria-current="page" >Attendance', false)
        ->assertSee('Reports and export')
        ->assertSee('href="#attendance-open-form"', false)
        ->assertSee('id="attendance-open-form"', false);
    $this->get(route('classes.meetings.index', $schoolClass))->assertOk()
        ->assertSee('class="is-active" aria-current="page" >Meetings', false)
        ->assertSee('class="class-empty"', false)
        ->assertSee('Schedule meeting');
});

test('student class pages use the same navigation without teacher actions', function (): void {
    $teacher = classroomUiUser('teacher');
    $student = classroomUiUser('student');
    $schoolClass = classroomUiClass($teacher, $student);

    $this->actingAs($student)->get(route('classes.coursework.index', $schoolClass))->assertOk()
        ->assertSee('class="is-active" aria-current="page" >Coursework', false)
        ->assertDontSee('Create assignment');
    $this->get(route('classes.attendance.index', $schoolClass))->assertOk()
        ->assertSee('class="is-active" aria-current="page" >Attendance', false)
        ->assertSee('My attendance')
        ->assertDontSee('Reports and export')
        ->assertDontSee('Open dated roster');
    $this->get(route('classes.meetings.index', $schoolClass))->assertOk()
        ->assertSee('class="is-active" aria-current="page" >Meetings', false)
        ->assertDontSee('Schedule meeting');
});

test('teacher section pages show a breadcrumb trail back to the class and section', function (): void {
    $teacher = classroomUiUser('teacher');
    $student = classroomUiUser('student');
    $schoolClass = classroomUiClass($teacher, $student);

    $this->actingAs($teacher)->get(route('classes.coursework.assignments.create', $schoolClass))->assertOk()
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('Create assignment')
        ->assertSee(route('classes.coursework.index', $schoolClass), false);
    $this->get(route('classes.attendance.report', $schoolClass))->assertOk()
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('Attendance report')
        ->assertSee(route('classes.attendance.index', $schoolClass), false);
    $this->get(route('classes.meetings.create', $schoolClass))->assertOk()
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('Schedule meeting')
        ->assertSee(route('classes.meetings.index', $schoolClass), false);
});
