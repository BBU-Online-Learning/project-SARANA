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
        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->string('type', 24);
            $table->text('prompt');
            $table->decimal('points', 8, 2)->default(1);
            $table->string('grading_mode', 16)->default('automatic');
            $table->json('accepted_answers')->nullable();
            $table->boolean('case_sensitive')->default(false);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->index(['quiz_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
