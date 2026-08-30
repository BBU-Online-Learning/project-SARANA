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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                ->constrained('chat_rooms')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
    |--------------------------------------------------------------------------
    | CLIENT RECONCILIATION
    |--------------------------------------------------------------------------
    */

            $table->uuid('client_uuid')
                ->nullable()
                ->index();
            

            $table->enum('message_type', [
                'voice',
                'image',
                'text',
                'video',
                'file',
                'system_notification'
            ])->default('text');

            $table->longText('body')->nullable();

            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->boolean('is_edited')
                ->default(false);

            $table->timestamp('edited_at')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('deleted_for_everyone_at')
                ->nullable();       

            $table->index(['room_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['message_type']);
            $table->index(['reply_to_message_id']);
            $table->index(['room_id', 'deleted_at']);
            $table->index([
                'room_id',
                'id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        
    }
};
