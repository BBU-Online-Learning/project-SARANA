<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['chat_rooms', 'chat_room_members', 'messages'] as $table) {
                DB::statement('ALTER TABLE '.$table.' ENGINE = InnoDB');
            }
        }

        DB::transaction(function (): void {
            DB::table('chat_rooms')->where('type', 'group')->orderBy('id')->chunkById(100, function ($rooms): void {
                foreach ($rooms as $room) {
                    if (DB::table('chat_room_members')->where('room_id', $room->id)->where('role', 'owner')->exists()) {
                        continue;
                    }
                    if (! $room->created_by || ! DB::table('users')->where('id', $room->created_by)->exists()) {
                        continue;
                    }
                    // Only the recorded creator's existing membership is reliable evidence.
                    DB::table('chat_room_members')->where('room_id', $room->id)->where('user_id', $room->created_by)
                        ->update(['role' => 'owner']);
                }
            });
        });
    }

    public function down(): void
    {
        // Ownership reconciliation and transactional storage are intentionally retained.
    }
};
