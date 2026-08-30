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
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                  ->constrained('chat_rooms')
                  ->cascadeOnDelete();

            $table->foreignId('initiated_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->enum('status', [
                'ongoing',
                'completed',
                'missed',
                'cancelled'
            ])->default('ongoing')
              ->index();

            $table->timestamp('started_at')
                  ->nullable()
                  ->index();

            $table->timestamp('ended_at')
                  ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
