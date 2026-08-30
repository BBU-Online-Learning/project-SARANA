<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_class_channel_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_class_channel_id')
                ->constrained('school_class_channels')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->uuid('client_uuid')
                ->nullable()
                ->unique();

            $table->longText('body');

            $table->boolean('is_edited')
                ->default(false);

            $table->timestamp('edited_at')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_class_channel_id', 'created_at'],'sc_channel_created_idx');
            $table->index(['sender_id', 'created_at'],'sc_sender_created_idx');
            $table->index(['school_class_channel_id', 'deleted_at'],'sc_channel_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_class_channel_messages');
    }
};
