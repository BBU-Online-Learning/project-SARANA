<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coursework_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->longText('instructions')->nullable();
            $table->decimal('max_points', 8, 2);
            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_resubmissions')->default(true);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->index(['school_class_id', 'status', 'due_at'], 'coursework_assignments_class_status_due');
            $table->timestamps();
        });

        Schema::create('coursework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coursework_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('latest_revision_number')->default(0);
            $table->timestamp('last_submitted_at')->nullable();
            $table->unique(['coursework_assignment_id', 'student_id'], 'coursework_one_submission_per_student');
            $table->index(['coursework_assignment_id', 'status'], 'coursework_submissions_assignment_status');
            $table->timestamps();
        });

        Schema::create('coursework_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coursework_submission_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('draft_slot')->nullable()->default(1);
            $table->longText('body')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_late')->nullable();
            $table->unique(['coursework_submission_id', 'revision_number'], 'coursework_revision_number_unique');
            $table->unique(['coursework_submission_id', 'draft_slot'], 'coursework_one_draft_per_submission');
            $table->timestamps();
        });

        Schema::create('coursework_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coursework_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path', 500)->unique();
            $table->string('original_name', 180);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });

        Schema::create('coursework_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coursework_submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('coursework_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('graded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('points_awarded', 8, 2);
            $table->decimal('max_points_snapshot', 8, 2);
            $table->longText('feedback')->nullable();
            $table->string('change_reason', 500)->nullable();
            $table->index(['coursework_submission_id', 'created_at'], 'coursework_grades_submission_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coursework_grades');
        Schema::dropIfExists('coursework_attachments');
        Schema::dropIfExists('coursework_revisions');
        Schema::dropIfExists('coursework_submissions');
        Schema::dropIfExists('coursework_assignments');
    }
};
