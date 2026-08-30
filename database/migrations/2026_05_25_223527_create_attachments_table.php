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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                  ->nullable()
                  ->constrained('messages')
                  ->cascadeOnDelete();

            $table->foreignId('room_id')
                  ->nullable()
                  ->constrained('chat_rooms')
                  ->cascadeOnDelete();

            $table->foreignId('uploaded_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('original_name');

            $table->string('storage_path');

            $table->string('mime_type')
                  ->nullable();

            $table->unsignedBigInteger('file_size')
                  ->default(0);

            $table->string('extension', 20)
                  ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['message_id']);
            $table->index(['room_id']);
            $table->index(['uploaded_by']);
            $table->index(['mime_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
