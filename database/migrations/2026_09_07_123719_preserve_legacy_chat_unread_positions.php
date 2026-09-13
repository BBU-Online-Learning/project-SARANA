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
        Schema::table('chat_room_members', function (Blueprint $table): void {
            $table->timestamp('legacy_read_at')->nullable();
        });
        \App\Models\ChatRoomMember::query()->whereNotNull('last_read_at')
            ->update(['legacy_read_at' => \App\Models\ChatRoomMember::resolveConnection()->raw('last_read_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_room_members', function (Blueprint $table): void {
            $table->dropColumn('legacy_read_at');
        });
    }
};
