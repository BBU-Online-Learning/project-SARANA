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
        Schema::create('quiz_assignment_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_assignment_id')->constrained('quiz_assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->unique(['quiz_assignment_id', 'user_id'], 'quiz_assignment_student_unique');
            $table->index(['user_id', 'quiz_assignment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_assignment_students');
    }
};
