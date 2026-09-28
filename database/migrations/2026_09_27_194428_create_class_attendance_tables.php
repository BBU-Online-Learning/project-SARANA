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
        Schema::create('class_attendance_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('roster_snapshot_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_class_id', 'attendance_date'], 'attendance_class_date_unique');
        });

        Schema::create('class_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_attendance_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('student_class_enrollment_id')->constrained()->restrictOnDelete();
            $table->string('student_name_snapshot');
            $table->string('status', 16)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['class_attendance_register_id', 'student_id'], 'attendance_register_student_unique');
        });

        Schema::create('class_attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_attendance_record_id')->constrained()->restrictOnDelete();
            $table->string('previous_status', 16);
            $table->string('new_status', 16);
            $table->text('previous_note')->nullable();
            $table->text('new_note')->nullable();
            $table->text('reason');
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_attendance_corrections');
        Schema::dropIfExists('class_attendance_records');
        Schema::dropIfExists('class_attendance_registers');
    }
};
