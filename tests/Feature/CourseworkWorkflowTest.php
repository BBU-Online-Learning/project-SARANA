<?php

use App\Models\AcademicYear;
use App\Models\CourseworkAssignment;
use App\Models\CourseworkAttachment;
use App\Models\CourseworkGrade;
use App\Models\CourseworkRevision;
use App\Models\CourseworkSubmission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\CourseworkDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function courseworkUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

function courseworkClass(User $teacher, User ...$students): SchoolClass
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    foreach ($students as $student) {
        $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    }

    return $schoolClass;
}

function publishedCoursework(SchoolClass $schoolClass, User $teacher, array $attributes = []): CourseworkAssignment
{
    return CourseworkAssignment::factory()->create($attributes + [
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
        'status' => 'published',
        'published_at' => now(),
    ]);
}

test('teacher publishes work and student submits revisions with immutable grade history', function (): void {
    $teacher = courseworkUser('teacher');
    $student = courseworkUser('student');
    $schoolClass = courseworkClass($teacher, $student);

    $this->actingAs($teacher)->post(route('classes.coursework.assignments.store', $schoolClass), [
        'title' => 'Essay on ecosystems', 'instructions' => 'Explain the food web.', 'max_points' => '20.00',
        'due_at' => now()->addDay()->format('Y-m-d H:i:s'), 'allow_resubmissions' => 1,
    ])->assertRedirect();
    $assignment = CourseworkAssignment::query()->firstOrFail();
    expect($assignment->status)->toBe('draft');
    $this->actingAs($student)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))->assertForbidden();
    $this->actingAs($student)->get(route('classes.coursework.index', $schoolClass))->assertOk()->assertDontSee('Essay on ecosystems');

    $this->actingAs($teacher)->post(route('classes.coursework.assignments.publish', [$schoolClass, $assignment]))->assertRedirect();
    expect($student->notifications()->firstOrFail()->data['category'])->toBe('coursework');
    $this->actingAs($student)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))->assertOk()->assertSee('Essay on ecosystems');
    expect(Gate::forUser($student)->allows('create', [CourseworkSubmission::class, $assignment->fresh()]))->toBeTrue();
    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), ['body' => 'First answer'])->assertRedirect()->assertSessionHasNoErrors();
    $submission = CourseworkSubmission::query()->firstOrFail();
    $this->actingAs($teacher)->get(route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]))->assertForbidden();
    $this->actingAs($teacher)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))->assertDontSee('First answer');

    $this->actingAs($student)->post(route('classes.coursework.submissions.submit', [$schoolClass, $assignment]))->assertRedirect();
    expect($submission->refresh()->status)->toBe('submitted')
        ->and($submission->revisions()->firstOrFail()->body)->toBe('First answer')
        ->and($teacher->notifications()->firstOrFail()->data['title'])->toBe('Coursework submitted');
    $this->actingAs($teacher)->get(route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]))
        ->assertOk()->assertSee('First answer')->assertSee('class="card class-list-card mb-3"', false)
        ->assertSee('class="class-status class-status--success"', false)->assertSee('Grade history');
    $this->actingAs($teacher)->post(route('classes.coursework.submissions.grade', [$schoolClass, $assignment, $submission]), [
        'points_awarded' => '17.50', 'feedback' => 'Good structure',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($teacher)->post(route('classes.coursework.submissions.grade', [$schoolClass, $assignment, $submission]), [
        'points_awarded' => '18.00', 'feedback' => 'Updated after review',
    ])->assertSessionHasErrors('change_reason');
    $this->actingAs($teacher)->post(route('classes.coursework.submissions.grade', [$schoolClass, $assignment, $submission]), [
        'points_awarded' => '18.00', 'feedback' => 'Updated after review', 'change_reason' => 'Rechecked the rubric',
    ])->assertSessionHasNoErrors();
    expect(CourseworkGrade::query()->count())->toBe(2)
        ->and(CourseworkGrade::query()->orderBy('id')->firstOrFail()->points_awarded)->toBe('17.50')
        ->and($student->notifications()->get()->pluck('data')->pluck('title'))->toContain('Coursework graded');
    $this->actingAs($teacher)->get(route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]))
        ->assertOk()->assertSee('Updated after review')->assertSee('Rechecked the rubric');
    $this->actingAs($student)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))->assertOk()->assertSee('Good structure')->assertSee('Updated after review');

    $this->actingAs($student)->post(route('classes.coursework.submissions.resubmit', [$schoolClass, $assignment]))->assertRedirect();
    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), ['body' => 'Revised answer'])->assertSessionHasNoErrors();
    $this->actingAs($student)->post(route('classes.coursework.submissions.submit', [$schoolClass, $assignment]))->assertRedirect();
    expect($submission->refresh()->latest_revision_number)->toBe(2)
        ->and($submission->revisions()->where('status', 'submitted')->count())->toBe(2)
        ->and($submission->revisions()->where('revision_number', 1)->firstOrFail()->body)->toBe('First answer')
        ->and($submission->revisions()->where('revision_number', 2)->firstOrFail()->body)->toBe('Revised answer');
    $this->actingAs($student)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))->assertSee('latest submitted revision awaits grading');
});

test('private attachments and submissions are scoped to their class and student', function (): void {
    Storage::fake('local');
    $teacher = courseworkUser('teacher');
    $student = courseworkUser('student');
    $otherStudent = courseworkUser('student');
    $otherTeacher = courseworkUser('teacher');
    $schoolClass = courseworkClass($teacher, $student, $otherStudent);
    $otherClass = courseworkClass($otherTeacher);
    $assignment = publishedCoursework($schoolClass, $teacher);
    $otherAssignment = publishedCoursework($otherClass, $otherTeacher);

    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), [
        'body' => 'Private response', 'attachments' => [UploadedFile::fake()->create('answer.txt', 1, 'text/plain')],
    ])->assertSessionHasNoErrors();
    $submission = CourseworkSubmission::query()->firstOrFail();
    $revision = CourseworkRevision::query()->firstOrFail();
    $attachment = CourseworkAttachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);
    $url = route('classes.coursework.attachments.download', [$schoolClass, $assignment, $submission, $revision, $attachment]);
    $download = $this->actingAs($student)->get($url)->assertOk();
    expect($download->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    expect(strtolower($download->headers->get('Content-Disposition')))->toContain('attachment');
    $this->actingAs($teacher)->get($url)->assertForbidden();
    $this->actingAs($otherStudent)->get($url)->assertForbidden();
    $this->actingAs($otherTeacher)->get($url)->assertForbidden();
    $this->actingAs($otherTeacher)->get(route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]))->assertForbidden();
    $this->actingAs($student)->get(route('classes.coursework.attachments.download', [$otherClass, $assignment, $submission, $revision, $attachment]))->assertNotFound();
    $this->actingAs($student)->get(route('classes.coursework.submissions.show', [$schoolClass, $otherAssignment, $submission]))->assertNotFound();

    $this->actingAs($student)->post(route('classes.coursework.submissions.submit', [$schoolClass, $assignment]))->assertRedirect();
    $this->actingAs($teacher)->get($url)->assertOk();
    $this->actingAs($student)->post(route('classes.coursework.submissions.grade', [$schoolClass, $assignment, $submission]), ['points_awarded' => 90])->assertForbidden();
    $this->actingAs($otherStudent)->get(route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]))->assertForbidden();
    $this->actingAs($student)->delete(route('classes.coursework.attachments.destroy', [$schoolClass, $assignment, $submission, $revision, $attachment]))->assertForbidden();

    $schoolClass->members()->detach($student->id);
    $this->actingAs($student)->get($url)->assertForbidden();
    $this->actingAs($student)->get(route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]))->assertForbidden();
    $attachment->update(['path' => 'class-avatars/unrelated.txt']);
    Storage::disk('local')->put('class-avatars/unrelated.txt', 'private data');
    $this->actingAs($teacher)->get($url)->assertNotFound();
});

test('draft attachment removal and submission validation preserve existing files', function (): void {
    Storage::fake('local');
    $teacher = courseworkUser('teacher');
    $student = courseworkUser('student');
    $schoolClass = courseworkClass($teacher, $student);
    $assignment = publishedCoursework($schoolClass, $teacher);

    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), [
        'attachments' => [UploadedFile::fake()->create('answer.txt', 1, 'text/plain')],
    ])->assertSessionHasNoErrors();
    $submission = CourseworkSubmission::query()->firstOrFail();
    $revision = CourseworkRevision::query()->firstOrFail();
    $attachment = CourseworkAttachment::query()->firstOrFail();
    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), [
        'attachments' => [UploadedFile::fake()->create('malware.php', 1, 'application/x-php')],
    ])->assertSessionHasErrors('attachments.0');
    expect(CourseworkAttachment::query()->count())->toBe(1);
    Storage::disk('local')->assertExists($attachment->path);
    $this->actingAs($student)->delete(route('classes.coursework.attachments.destroy', [$schoolClass, $assignment, $submission, $revision, $attachment]))->assertRedirect();
    expect(CourseworkAttachment::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
    $this->actingAs($student)->post(route('classes.coursework.submissions.submit', [$schoolClass, $assignment]))->assertSessionHasErrors('submission');
});

test('closed and archived classes block new work while teachers can still view submitted history', function (): void {
    $teacher = courseworkUser('teacher');
    $student = courseworkUser('student');
    $schoolClass = courseworkClass($teacher, $student);
    $assignment = publishedCoursework($schoolClass, $teacher, ['allow_resubmissions' => false]);
    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), ['body' => 'Completed work'])->assertSessionHasNoErrors();
    $this->actingAs($student)->post(route('classes.coursework.submissions.submit', [$schoolClass, $assignment]))->assertRedirect();
    $submission = CourseworkSubmission::query()->firstOrFail();
    $this->actingAs($student)->post(route('classes.coursework.submissions.resubmit', [$schoolClass, $assignment]))->assertForbidden();
    $this->actingAs($teacher)->post(route('classes.coursework.assignments.close', [$schoolClass, $assignment]))->assertRedirect();
    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), ['body' => 'Late edit'])->assertForbidden();
    $this->actingAs($teacher)->get(route('classes.coursework.submissions.show', [$schoolClass, $assignment, $submission]))->assertOk()->assertSee('Completed work');
    $schoolClass->forceFill(['archived_at' => now()])->save();
    $this->actingAs($teacher)->post(route('classes.coursework.submissions.grade', [$schoolClass, $assignment, $submission]), ['points_awarded' => 80])->assertForbidden();
    $this->actingAs($student)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))->assertOk();
});

test('demo coursework seeder is opt in and does not fabricate submissions', function (): void {
    $teacher = courseworkUser('teacher');
    courseworkClass($teacher);
    $this->seed(CourseworkDemoSeeder::class);
    $this->seed(CourseworkDemoSeeder::class);

    expect(CourseworkAssignment::query()->where('title', 'Demo written reflection')->count())->toBe(1)
        ->and(CourseworkSubmission::query()->count())->toBe(0);
});

test('coursework keeps its academic year and accepts only subjects assigned to its class', function (): void {
    $teacher = courseworkUser('teacher');
    $schoolClass = courseworkClass($teacher);
    $year = AcademicYear::factory()->create(['name' => 'Current coursework year']);
    $nextYear = AcademicYear::factory()->create(['name' => 'Next coursework year']);
    $schoolClass->update(['academic_year_id' => $year->id]);
    $subject = Subject::factory()->create();
    $otherSubject = Subject::factory()->create();
    $schoolClass->subjects()->attach($subject);
    $payload = ['title' => 'Subject reflection', 'max_points' => 10, 'allow_resubmissions' => 1];

    $this->actingAs($teacher)->post(route('classes.coursework.assignments.store', $schoolClass), $payload + ['subject_id' => $otherSubject->id])->assertSessionHasErrors('subject_id');
    expect(CourseworkAssignment::query()->count())->toBe(0);
    $this->actingAs($teacher)->post(route('classes.coursework.assignments.store', $schoolClass), $payload + ['subject_id' => $subject->id])->assertRedirect();
    $assignment = CourseworkAssignment::query()->firstOrFail();
    $schoolClass->update(['academic_year_id' => $nextYear->id]);

    expect($assignment->fresh()->academic_year_id)->toBe($year->id)
        ->and($assignment->fresh()->subject_id)->toBe($subject->id)
        ->and($schoolClass->fresh()->academic_year_id)->toBe($nextYear->id);
});

test('late submissions are marked and draft file limits include existing attachments', function (): void {
    Storage::fake('local');
    $teacher = courseworkUser('teacher');
    $student = courseworkUser('student');
    $schoolClass = courseworkClass($teacher, $student);
    $assignment = publishedCoursework($schoolClass, $teacher, ['due_at' => now()->subDay()]);
    $files = [];
    for ($number = 1; $number <= 5; $number++) {
        $files[] = UploadedFile::fake()->create('note'.$number.'.txt', 1, 'text/plain');
    }

    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), [
        'body' => 'Late work', 'attachments' => $files,
    ])->assertSessionHasNoErrors();
    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), [
        'body' => 'Should not replace the draft', 'attachments' => [UploadedFile::fake()->create('sixth.txt', 1, 'text/plain')],
    ])->assertSessionHasErrors('attachments');
    $revision = CourseworkRevision::query()->firstOrFail();
    expect($revision->fresh()->body)->toBe('Late work')
        ->and($revision->attachments()->count())->toBe(5);
    $this->actingAs($student)->post(route('classes.coursework.submissions.submit', [$schoolClass, $assignment]))->assertRedirect();
    expect($revision->fresh()->is_late)->toBeTrue();
});

test('coursework model factories build connected grade history', function (): void {
    $submission = CourseworkSubmission::factory()->create();
    $grade = CourseworkGrade::factory()->create(['coursework_submission_id' => $submission->id]);

    expect($grade->revision->submission->is($submission))->toBeTrue()
        ->and($grade->submission->is($submission))->toBeTrue();
});

test('assignment editing is teacher only and stops when published', function (): void {
    $teacher = courseworkUser('teacher');
    $student = courseworkUser('student');
    $outsider = courseworkUser('teacher');
    $schoolClass = courseworkClass($teacher, $student);
    $assignment = CourseworkAssignment::factory()->create([
        'school_class_id' => $schoolClass->id,
        'created_by' => $teacher->id,
    ]);
    $payload = ['title' => 'Updated prompt', 'max_points' => 25, 'allow_resubmissions' => 0];

    $this->actingAs($student)->post(route('classes.coursework.assignments.store', $schoolClass), $payload)->assertForbidden();
    $this->actingAs($student)->patch(route('classes.coursework.assignments.update', [$schoolClass, $assignment]), $payload)->assertForbidden();
    $this->actingAs($outsider)->get(route('classes.coursework.index', $schoolClass))->assertForbidden();
    $this->actingAs($teacher)->patch(route('classes.coursework.assignments.update', [$schoolClass, $assignment]), $payload)->assertRedirect();
    expect($assignment->fresh()->title)->toBe('Updated prompt');
    $this->actingAs($teacher)->post(route('classes.coursework.assignments.publish', [$schoolClass, $assignment]))->assertRedirect();
    $this->actingAs($teacher)->patch(route('classes.coursework.assignments.update', [$schoolClass, $assignment]), $payload)->assertForbidden();
    $this->actingAs($teacher)->post(route('classes.coursework.assignments.publish', [$schoolClass, $assignment]))->assertForbidden();
});

test('coursework labels deadline timezone and explains the draft submission flow', function (): void {
    $teacher = courseworkUser('teacher');
    $student = courseworkUser('student');
    $schoolClass = courseworkClass($teacher, $student);
    $assignment = publishedCoursework($schoolClass, $teacher, ['due_at' => now()->addDay()->startOfMinute()]);
    $timezone = config('app.timezone');

    $this->actingAs($teacher)->get(route('classes.coursework.assignments.create', $schoolClass))
        ->assertOk()
        ->assertSee('Due date (optional, '.$timezone.' time)');
    $this->actingAs($student)->get(route('classes.coursework.index', $schoolClass))
        ->assertOk()
        ->assertSee('class="class-status class-status--success"', false)
        ->assertSee('Due '.$assignment->due_at->format('Y-m-d H:i').' '.$timezone);
    $this->actingAs($student)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))
        ->assertOk()
        ->assertSee('Due '.$assignment->due_at->format('Y-m-d H:i').' '.$timezone)
        ->assertSee('aria-label="Coursework submission steps"', false)
        ->assertSee('Save a private draft')
        ->assertSee('Saving a draft alone does not submit it.')
        ->assertDontSee('Submit response</button>', false);

    $this->actingAs($student)->post(route('classes.coursework.drafts.save', [$schoolClass, $assignment]), ['body' => 'Draft response'])
        ->assertSessionHasNoErrors();
    $this->actingAs($student)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))
        ->assertOk()
        ->assertSee('Review your draft and attachments before submitting.')
        ->assertSee('Submit response</button>', false);
    $this->actingAs($student)->post(route('classes.coursework.submissions.submit', [$schoolClass, $assignment]))
        ->assertSessionHasNoErrors();
    $this->actingAs($student)->get(route('classes.coursework.assignments.show', [$schoolClass, $assignment]))
        ->assertOk()
        ->assertSee('Submission recorded')
        ->assertSee('class="is-done"', false);
});
