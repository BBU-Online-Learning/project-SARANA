<?php

use App\Models\AcademicYear;
use App\Models\CourseworkAssignment;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Services\ClassManagementService;
use App\Services\SubjectTeacherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function subjectTeacherUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function subjectTeacherClass(User $owner, User ...$members): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $owner->id]);
    $schoolClass->members()->attach($owner, ['role' => 'owner', 'joined_at' => now()]);
    foreach ($members as $member) {
        $schoolClass->members()->attach($member, [
            'role' => $member->role->name === Role::STUDENT ? 'student' : 'teacher',
            'joined_at' => now(),
        ]);
    }

    return $schoolClass;
}

test('subject teacher assignments restrict subject coursework while preserving general work', function (): void {
    $owner = subjectTeacherUser('teacher');
    $assignedTeacher = subjectTeacherUser('teacher');
    $otherTeacher = subjectTeacherUser('teacher');
    $student = subjectTeacherUser('student');
    $schoolClass = subjectTeacherClass($owner, $assignedTeacher, $otherTeacher, $student);
    $subject = Subject::factory()->create();
    $schoolClass->subjects()->attach($subject);
    $service = app(SubjectTeacherService::class);

    expect($service->canManageCoursework($otherTeacher, $schoolClass, $subject->id))->toBeTrue();
    $this->actingAs($owner)->post(route('classes.subject-teachers.store', $schoolClass), [
        'subject_id' => $subject->id, 'user_id' => $assignedTeacher->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $assignment = TeacherSubjectAssignment::query()->sole();
    $this->actingAs($owner)->get(route('classes.show', $schoolClass))->assertOk()
        ->assertSee('Subject teachers')->assertSee($assignedTeacher->name);
    expect($service->canManageCoursework($assignedTeacher, $schoolClass, $subject->id))->toBeTrue()
        ->and($service->canManageCoursework($otherTeacher, $schoolClass, $subject->id))->toBeFalse()
        ->and($service->canManageCoursework($otherTeacher, $schoolClass, null))->toBeTrue();

    $payload = ['title' => 'Subject essay', 'max_points' => '20', 'subject_id' => $subject->id, 'allow_resubmissions' => 1];
    $this->actingAs($otherTeacher)->post(route('classes.coursework.assignments.store', $schoolClass), $payload)->assertForbidden();
    $this->actingAs($assignedTeacher)->post(route('classes.coursework.assignments.store', $schoolClass), $payload)->assertRedirect();
    $coursework = CourseworkAssignment::query()->sole();
    expect(Gate::forUser($otherTeacher)->allows('update', $coursework))->toBeFalse()
        ->and(Gate::forUser($otherTeacher)->allows('view', $coursework))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('update', $coursework))->toBeTrue();
    $this->actingAs($otherTeacher)->get(route('classes.coursework.index', $schoolClass))->assertDontSee('Subject essay');
    $this->actingAs($otherTeacher)->get(route('classes.coursework.assignments.show', [$schoolClass, $coursework]))->assertForbidden();
    $this->actingAs($assignedTeacher)->post(route('classes.coursework.assignments.publish', [$schoolClass, $coursework]))->assertRedirect();
    $this->actingAs($otherTeacher)->get(route('classes.coursework.assignments.show', [$schoolClass, $coursework]))
        ->assertOk()->assertSee('A teacher assigned to this subject can review');
    $this->actingAs($otherTeacher)->post(route('classes.subject-teachers.store', $schoolClass), [
        'subject_id' => $subject->id, 'user_id' => $otherTeacher->id,
    ])->assertForbidden();
    $this->actingAs($student)->post(route('classes.subject-teachers.end', [$schoolClass, $assignment]))->assertForbidden();

    $this->actingAs($owner)->post(route('classes.subject-teachers.end', [$schoolClass, $assignment]))->assertRedirect();
    expect($service->canManageCoursework($otherTeacher, $schoolClass, $subject->id))->toBeTrue();
    $this->actingAs($otherTeacher)->post(route('classes.coursework.assignments.store', $schoolClass), $payload)->assertRedirect();
});

test('subject teacher history survives removal, year changes, and subject removal', function (): void {
    $owner = subjectTeacherUser('teacher');
    $teacher = subjectTeacherUser('teacher');
    $admin = subjectTeacherUser('admin');
    $schoolClass = subjectTeacherClass($owner, $teacher);
    $subject = Subject::factory()->create();
    $schoolClass->subjects()->attach($subject);
    $service = app(SubjectTeacherService::class);
    $first = $service->assign($owner, $schoolClass, $subject->id, $teacher->id);

    $newYear = AcademicYear::factory()->create();
    $this->actingAs($admin)->patch(route('academics.classes.update', $schoolClass), [
        'academic_year_id' => $newYear->id, 'subject_ids' => [$subject->id],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($first->fresh()->end_reason)->toBe('academic_year_changed')
        ->and($schoolClass->subjectTeacherAssignments()->where('active_slot', 1)->sole()->academic_year_id)->toBe($newYear->id);

    app(ClassManagementService::class)->remove($owner, $schoolClass, $teacher);
    expect($schoolClass->subjectTeacherAssignments()->where('active_slot', 1)->count())->toBe(0)
        ->and($schoolClass->subjectTeacherAssignments()->latest('id')->firstOrFail()->end_reason)->toBe('member_removed');
    $this->actingAs($owner)->post(route('classes.subject-teachers.store', $schoolClass), [
        'subject_id' => $subject->id, 'user_id' => $teacher->id,
    ])->assertSessionHasErrors('user_id');

    $replacement = subjectTeacherUser('teacher');
    $schoolClass->members()->attach($replacement, ['role' => 'teacher', 'joined_at' => now()]);
    $service->assign($owner, $schoolClass, $subject->id, $replacement->id);
    $this->actingAs($admin)->patch(route('academics.classes.update', $schoolClass), [
        'academic_year_id' => $newYear->id, 'subject_ids' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($schoolClass->subjectTeacherAssignments()->where('active_slot', 1)->count())->toBe(0)
        ->and($schoolClass->subjectTeacherAssignments()->latest('id')->firstOrFail()->end_reason)->toBe('subject_removed')
        ->and($schoolClass->subjects()->count())->toBe(0);
});

test('only the current subject teacher or owner can grade submitted subject work', function (): void {
    $owner = subjectTeacherUser('teacher');
    $assignedTeacher = subjectTeacherUser('teacher');
    $otherTeacher = subjectTeacherUser('teacher');
    $student = subjectTeacherUser('student');
    $schoolClass = subjectTeacherClass($owner, $assignedTeacher, $otherTeacher, $student);
    $subject = Subject::factory()->create();
    $schoolClass->subjects()->attach($subject);
    app(SubjectTeacherService::class)->assign($owner, $schoolClass, $subject->id, $assignedTeacher->id);
    $assignment = CourseworkAssignment::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $owner->id,
        'subject_id' => $subject->id,
        'status' => 'published',
        'published_at' => now(),
    ]);
    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), ['body' => 'My answer'])
        ->assertRedirect();
    $this->post(route('classes.coursework.submissions.submit', [$schoolClass, $assignment]))->assertRedirect();
    $submission = $assignment->submissions()->sole();

    $this->actingAs($otherTeacher)->get(route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]))->assertForbidden();
    $this->post(route('classes.coursework.submissions.grade', [$schoolClass, $assignment, $submission]), [
        'points_awarded' => '8', 'feedback' => 'Review',
    ])->assertForbidden();
    $this->actingAs($assignedTeacher)->post(route('classes.coursework.submissions.grade', [$schoolClass, $assignment, $submission]), [
        'points_awarded' => '8', 'feedback' => 'Good work',
    ])->assertRedirect();
    expect($submission->grades()->count())->toBe(1);
});
