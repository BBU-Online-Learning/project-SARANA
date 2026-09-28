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
        Schema::create('class_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('series_key');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('recurrence', 12)->default('none');
            $table->unsignedSmallInteger('occurrence_number')->default(1);
            $table->unsignedSmallInteger('occurrence_count')->default(1);
            $table->dateTime('original_starts_at');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 12)->default('scheduled');
            $table->dateTime('rescheduled_at')->nullable();
            $table->foreignId('rescheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series_key', 'occurrence_number']);
            $table->index(['school_class_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_meetings');
    }
};
