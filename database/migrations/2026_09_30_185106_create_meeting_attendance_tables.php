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
        Schema::create('meeting_attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_meeting_id')->constrained('class_meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('room_sid', 128);
            $table->string('participant_sid', 128);
            $table->dateTime('joined_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->string('leave_reason', 32)->nullable();
            $table->timestamps();
            $table->unique(['class_meeting_id', 'participant_sid']);
            $table->index(['class_meeting_id', 'user_id']);
        });

        Schema::create('meeting_attendance_webhook_events', function (Blueprint $table): void {
            $table->string('id', 128)->primary();
            $table->foreignId('class_meeting_id')->constrained('class_meetings')->cascadeOnDelete();
            $table->string('room_sid', 128);
            $table->string('event', 32);
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['class_meeting_id', 'room_sid', 'event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_attendance_webhook_events');
        Schema::dropIfExists('meeting_attendance_sessions');
    }
};
