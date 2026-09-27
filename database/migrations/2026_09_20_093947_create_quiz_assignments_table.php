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
        Schema::create('quiz_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('instructions')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('due_at');
            $table->unsignedSmallInteger('attempt_limit')->default(1);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('results_release', 20)->default('immediate');
            $table->boolean('show_correct_answers')->default(false);
            $table->timestamp('results_released_at')->nullable();
            $table->timestamps();
            $table->index(['school_class_id', 'status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_assignments');
    }
};
