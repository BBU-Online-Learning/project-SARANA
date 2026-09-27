<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function quizWorkflowClass(): array
{
    $teacher = securityTestUser('teacher');
    $student = securityTestUser('student');
    $schoolClass = SchoolClass::query()->create(['name' => 'Web Development', 'join_code' => 'QUIZTEST2026', 'description' => 'Assessment test class', 'created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);

    return [$teacher, $student, $schoolClass];
}

test('assessment overview gives clear next steps when there are no classes or assignments', function (): void {
    $teacher = securityTestUser('teacher');
    $student = securityTestUser('student');

    $this->actingAs($teacher)->get(route('assessments.index'))->assertOk()
        ->assertSee('No teaching classes yet')
        ->assertSee(route('classes.index'), false)
        ->assertDontSee('Recent assessments');

    $this->actingAs($student)->get(route('assessments.index'))->assertOk()
        ->assertSee('No quizzes assigned yet')
        ->assertSee(route('classes.index'), false);
});

test('teacher can build assign and automatically grade a quiz', function (): void {
    [$teacher, $student, $schoolClass] = quizWorkflowClass();
    $this->actingAs($teacher)->get(route('chat.index'))
        ->assertOk()
        ->assertSee(asset('css/quiz.css'), false);
    $this->actingAs($teacher)->get(route('assessments.index'))
        ->assertOk()
        ->assertSee('Build, assign, and review quizzes for your classes.')
        ->assertSee('Choose a class')
        ->assertSee('Web Development')
        ->assertSee('Create quiz')
        ->assertSee(route('classes.quizzes.create', $schoolClass), false);
    $this->actingAs($teacher)->post(route('classes.quizzes.store', $schoolClass), ['title' => 'Laravel Basics', 'description' => 'Framework knowledge'])->assertRedirect();
    $quiz = Quiz::query()->firstOrFail();
    $this->actingAs($teacher)->get(route('assessments.index'))->assertOk()
        ->assertSee('Continue building')
        ->assertSee(route('classes.quizzes.edit', [$schoolClass, $quiz]), false);
    $this->actingAs($teacher)->post(route('classes.quizzes.questions.store', [$schoolClass, $quiz]), [
        'type' => 'multiple_choice', 'prompt' => 'What is Laravel?', 'points' => 2, 'grading_mode' => 'automatic',
        'options' => [['text' => 'Operating system'], ['text' => 'PHP framework'], ['text' => 'Database']], 'correct_options' => [1],
    ])->assertRedirect();
    $question = $quiz->questions()->with('options')->firstOrFail();
    $correctOption = $question->options->firstWhere('is_correct', true);
    $this->actingAs($teacher)->post(route('classes.quizzes.publish', [$schoolClass, $quiz]))->assertRedirect();
    $this->actingAs($teacher)->get(route('assessments.index'))->assertOk()
        ->assertSee('Assign quiz')
        ->assertSee(route('classes.quizzes.assign.create', [$schoolClass, $quiz]), false);
    $this->actingAs($teacher)->post(route('classes.quizzes.assign.store', [$schoolClass, $quiz]), [
        'starts_at' => now()->subMinute()->toDateTimeString(), 'due_at' => now()->addDay()->toDateTimeString(), 'attempt_limit' => 1,
        'time_limit_minutes' => 30, 'results_release' => 'immediate', 'show_correct_answers' => 1, 'student_ids' => [$student->id],
    ])->assertRedirect();
    $assignment = $quiz->assignments()->firstOrFail();
    $this->actingAs($teacher)->get(route('assessments.index'))->assertOk()
        ->assertSee('Review results')
        ->assertSee(route('classes.quiz-assignments.results', [$schoolClass, $assignment]), false);
    $this->actingAs($student)->get(route('assessments.index'))->assertOk()
        ->assertSee('Start quiz')
        ->assertSee(route('classes.quiz-assignments.start', [$schoolClass, $assignment]), false);
    expect($student->notifications()->firstOrFail()->data['category'])->toBe('quiz');
    $this->actingAs($student)->post(route('classes.quiz-assignments.start', [$schoolClass, $assignment]))->assertRedirect();
    $attempt = QuizAttempt::query()->firstOrFail();
    $this->actingAs($student)->get(route('assessments.index'))->assertOk()
        ->assertSee('Continue quiz')
        ->assertSee(route('classes.quiz-attempts.show', [$schoolClass, $attempt]), false);
    $this->actingAs($student)->get(route('classes.quiz-attempts.show', [$schoolClass, $attempt]))->assertOk()->assertSee('What is Laravel?')->assertDontSee('is_correct');
    $this->actingAs($student)->post(route('classes.quiz-attempts.submit', [$schoolClass, $attempt]), ['answers' => [$question->id => ['options' => [$correctOption->id]]]])
        ->assertRedirect(route('classes.quiz-attempts.result', [$schoolClass, $attempt]));
    $attempt->refresh();
    expect($attempt->status)->toBe('graded')->and((float) $attempt->earned_points)->toBe(2.0)->and((float) $attempt->percentage)->toBe(100.0);
    $this->actingAs($student)->get(route('home'))
        ->assertOk()
        ->assertViewHas('studentAssessmentSummary', ['assigned' => 1, 'available' => 1, 'in_progress' => 0, 'graded' => 1])
        ->assertSee('Learning Overview')
        ->assertSee('Laravel Basics')
        ->assertSee('Web Development');
    $this->actingAs($student)->get(route('classes.quiz-attempts.result', [$schoolClass, $attempt]))->assertOk()->assertSee('2.00/2.00')->assertSee('PHP framework');
    $this->actingAs($student)->get(route('assessments.index'))->assertOk()
        ->assertSee('Laravel Basics')
        ->assertSee('Web Development')
        ->assertSee('View submission')
        ->assertSee(route('classes.quiz-attempts.result', [$schoolClass, $attempt]), false);
    $this->actingAs($teacher)->get(route('classes.quiz-assignments.results', [$schoolClass, $assignment]))->assertOk()->assertSee('100.00%');
});

test('students cannot manage quizzes and manual questions require teacher review', function (): void {
    [$teacher, $student, $schoolClass] = quizWorkflowClass();
    $quiz = Quiz::query()->create(['school_class_id' => $schoolClass->id, 'creator_id' => $teacher->id, 'title' => 'Reflection', 'status' => 'published', 'published_at' => now()]);
    $question = $quiz->questions()->create(['type' => 'short_answer', 'prompt' => 'Explain MVC.', 'points' => 5, 'grading_mode' => 'manual', 'position' => 1]);
    $assignment = $quiz->assignments()->create(['school_class_id' => $schoolClass->id, 'assigned_by' => $teacher->id, 'starts_at' => now()->subMinute(), 'due_at' => now()->addDay(), 'attempt_limit' => 1, 'status' => 'scheduled', 'results_release' => 'manual']);
    $assignment->students()->attach($student, ['assigned_at' => now()]);
    $this->actingAs($student)->get(route('classes.quizzes.edit', [$schoolClass, $quiz]))->assertForbidden();
    $this->actingAs($student)->post(route('classes.quiz-assignments.start', [$schoolClass, $assignment]))->assertRedirect();
    $attempt = QuizAttempt::query()->firstOrFail();
    $this->actingAs($student)->post(route('classes.quiz-attempts.submit', [$schoolClass, $attempt]), ['answers' => [$question->id => ['text' => 'Model View Controller']]])->assertRedirect();
    expect($attempt->refresh()->status)->toBe('pending_review');
    expect($teacher->notifications()->firstOrFail()->data['title'])->toBe('Quiz needs grading');
    $this->actingAs($teacher)->get(route('home'))
        ->assertOk()
        ->assertViewHas('assessmentSummary', ['quizzes' => 1, 'active_assignments' => 1, 'pending_reviews' => 1])
        ->assertSee('Teaching Overview')
        ->assertSee('Reflection')
        ->assertSee('Needs grading');
    $answer = $attempt->answers()->firstOrFail();
    $this->actingAs($teacher)->get(route('classes.quiz-assignments.results.show', [$schoolClass, $assignment, $attempt]))->assertOk()->assertSee('Model View Controller');
    $this->actingAs($teacher)->patch(route('classes.quiz-assignments.results.update', [$schoolClass, $assignment, $attempt]), [
        'grades' => [$answer->id => ['points' => 4, 'feedback' => 'Good explanation']], 'feedback' => 'Review the controller responsibility.',
    ])->assertRedirect(route('classes.quiz-assignments.results', [$schoolClass, $assignment]));
    expect($attempt->refresh()->status)->toBe('graded')->and((float) $attempt->earned_points)->toBe(4.0)->and((float) $attempt->percentage)->toBe(80.0);
    $this->post(route('classes.quiz-assignments.release', [$schoolClass, $assignment]))->assertRedirect();
    $this->post(route('classes.quiz-assignments.release', [$schoolClass, $assignment]))->assertRedirect();
    expect($student->notifications()->count())->toBe(1)
        ->and($student->notifications()->firstOrFail()->data['title'])->toBe('Quiz results available');
});

test('an expired attempt submits saved answers without accepting late changes', function (): void {
    [$teacher, $student, $schoolClass] = quizWorkflowClass();
    $quiz = $schoolClass->quizzes()->create(['creator_id' => $teacher->id, 'title' => 'Deadline quiz', 'status' => 'published', 'published_at' => now()]);
    $question = $quiz->questions()->create(['type' => 'multiple_choice', 'prompt' => 'Choose the answer', 'points' => 2, 'grading_mode' => 'automatic', 'position' => 1]);
    $wrong = $question->options()->create(['text' => 'Wrong', 'is_correct' => false, 'position' => 1]);
    $correct = $question->options()->create(['text' => 'Correct', 'is_correct' => true, 'position' => 2]);
    $assignment = $quiz->assignments()->create(['school_class_id' => $schoolClass->id, 'assigned_by' => $teacher->id, 'starts_at' => now()->subDay(), 'due_at' => now()->addDay(), 'attempt_limit' => 1, 'status' => 'scheduled', 'results_release' => 'immediate']);
    $assignment->students()->attach($student, ['assigned_at' => now()]);
    $attempt = $assignment->attempts()->create(['user_id' => $student->id, 'attempt_number' => 1, 'status' => 'in_progress', 'started_at' => now()->subHour(), 'deadline_at' => now()->subMinute(), 'total_points' => 2]);
    $answer = $attempt->answers()->create(['quiz_question_id' => $question->id, 'selected_option_ids' => [$wrong->id]]);
    $lateAnswers = ['answers' => [$question->id => ['options' => [$correct->id]]]];

    $this->actingAs($student)->patchJson(route('classes.quiz-attempts.save', [$schoolClass, $attempt]), $lateAnswers)->assertStatus(409);
    $this->post(route('classes.quiz-attempts.submit', [$schoolClass, $attempt]), $lateAnswers)
        ->assertRedirect(route('classes.quiz-attempts.result', [$schoolClass, $attempt]))
        ->assertSessionHas('warning');

    expect($attempt->fresh()->status)->toBe('graded')
        ->and((float) $attempt->fresh()->earned_points)->toBe(0.0)
        ->and($answer->fresh()->selected_option_ids)->toBe([$wrong->id]);

    $this->patchJson(route('classes.quiz-attempts.save', [$schoolClass, $attempt]), $lateAnswers)->assertForbidden();
    expect($answer->fresh()->points_awarded)->toBe('0.00');
});

test('teachers cannot review or grade an attempt before the student submits it', function (): void {
    [$teacher, $student, $schoolClass] = quizWorkflowClass();
    $quiz = $schoolClass->quizzes()->create(['creator_id' => $teacher->id, 'title' => 'Open quiz', 'status' => 'published', 'published_at' => now()]);
    $question = $quiz->questions()->create(['type' => 'short_answer', 'prompt' => 'Explain the topic', 'points' => 5, 'grading_mode' => 'manual', 'position' => 1]);
    $assignment = $quiz->assignments()->create(['school_class_id' => $schoolClass->id, 'assigned_by' => $teacher->id, 'starts_at' => now()->subMinute(), 'due_at' => now()->addDay(), 'attempt_limit' => 1, 'status' => 'scheduled', 'results_release' => 'manual']);
    $assignment->students()->attach($student, ['assigned_at' => now()]);
    $attempt = $assignment->attempts()->create(['user_id' => $student->id, 'attempt_number' => 1, 'status' => 'in_progress', 'started_at' => now(), 'deadline_at' => now()->addDay(), 'total_points' => 5]);
    $answer = $attempt->answers()->create(['quiz_question_id' => $question->id, 'answer_text' => 'Draft answer']);

    $this->actingAs($teacher)->get(route('classes.quiz-assignments.results.show', [$schoolClass, $assignment, $attempt]))->assertNotFound();
    $this->patch(route('classes.quiz-assignments.results.update', [$schoolClass, $assignment, $attempt]), [
        'grades' => [$answer->id => ['points' => 5]],
    ])->assertStatus(409);

    expect($attempt->fresh()->status)->toBe('in_progress')
        ->and($answer->fresh()->points_awarded)->toBeNull();
});

test('results count students once while listing each submitted attempt', function (): void {
    [$teacher, $student, $schoolClass] = quizWorkflowClass();
    $quiz = $schoolClass->quizzes()->create(['creator_id' => $teacher->id, 'title' => 'Retake quiz', 'status' => 'published', 'published_at' => now()]);
    $assignment = $quiz->assignments()->create(['school_class_id' => $schoolClass->id, 'assigned_by' => $teacher->id, 'starts_at' => now()->subDay(), 'due_at' => now()->addDay(), 'attempt_limit' => 2, 'status' => 'scheduled', 'results_release' => 'immediate']);
    $assignment->students()->attach($student, ['assigned_at' => now()]);
    foreach ([1, 2] as $attemptNumber) {
        $assignment->attempts()->create(['user_id' => $student->id, 'attempt_number' => $attemptNumber, 'status' => 'graded', 'started_at' => now()->subHour(), 'submitted_at' => now(), 'total_points' => 1, 'earned_points' => 1, 'percentage' => 100]);
    }

    $this->actingAs($teacher)->get(route('classes.quiz-assignments.results', [$schoolClass, $assignment]))->assertOk()
        ->assertViewHas('submittedStudentCount', 1)
        ->assertSee('Average attempt')
        ->assertSee('Submissions')
        ->assertSee('Attempt');
});
