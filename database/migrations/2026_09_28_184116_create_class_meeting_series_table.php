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
        Schema::create('class_meeting_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('series_key')->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('recurrence', 20);
            $table->json('weekdays')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->time('local_start_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('timezone', 64);
            $table->date('generated_through')->nullable();
            $table->string('status', 12)->default('active');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'generated_through']);
        });

        Schema::table('class_meetings', function (Blueprint $table) {
            $table->foreignId('class_meeting_series_id')->nullable()->constrained('class_meeting_series')->restrictOnDelete();
            $table->date('series_occurrence_on')->nullable();
            $table->timestamp('series_override_at')->nullable();
            $table->unique(['class_meeting_series_id', 'series_occurrence_on'], 'class_meeting_series_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_meetings', function (Blueprint $table) {
            $table->dropUnique('class_meeting_series_date_unique');
            $table->dropConstrainedForeignId('class_meeting_series_id');
            $table->dropColumn(['series_occurrence_on', 'series_override_at']);
        });
        Schema::dropIfExists('class_meeting_series');
    }
};
