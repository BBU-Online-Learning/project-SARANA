<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_class_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_class_id')
                ->constrained('school_classes')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('role', ['owner', 'teacher', 'student'])
                ->default('student');

            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(['school_class_id', 'user_id']);
            $table->index(['user_id', 'school_class_id']);
            $table->index(['school_class_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_class_members');
    }
};