<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (['call_sessions', 'call_participants'] as $tableName) {
                $table = DB::connection()->getQueryGrammar()->wrapTable($tableName);
                DB::statement("ALTER TABLE {$table} ENGINE = InnoDB");
            }
        }

        Schema::table('call_sessions', function (Blueprint $table) {
            $table->enum('status', [
                'ringing',
                'active',
                'declined',
                'missed',
                'cancelled',
                'ended',
                'failed',
                'ongoing',
                'completed',
            ])->default('ringing')->change();
        });

        if (! Schema::hasColumn('call_sessions', 'answered_at')) {
            Schema::table('call_sessions', fn (Blueprint $table) => $table->timestamp('answered_at')->nullable()->after('started_at'));
        }
        if (! Schema::hasColumn('call_sessions', 'expires_at')) {
            Schema::table('call_sessions', fn (Blueprint $table) => $table->timestamp('expires_at')->nullable()->after('answered_at')->index());
        }
        if (! Schema::hasColumn('call_sessions', 'end_reason')) {
            Schema::table('call_sessions', fn (Blueprint $table) => $table->string('end_reason', 64)->nullable()->after('ended_at'));
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->enum('message_type', [
                'voice',
                'image',
                'text',
                'video',
                'file',
                'sticker',
                'call',
                'system_notification',
            ])->default('text')->change();
        });

        if (! Schema::hasColumn('messages', 'call_session_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreignId('call_session_id')->nullable()->after('room_id')
                    ->unique()->constrained('call_sessions')->nullOnDelete();
            });
        } elseif (DB::connection()->getDriverName() === 'mysql') {
            $databaseName = DB::connection()->getDatabaseName();
            $hasUniqueIndex = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', $databaseName)
                ->where('TABLE_NAME', 'messages')
                ->where('COLUMN_NAME', 'call_session_id')
                ->where('NON_UNIQUE', 0)
                ->exists();
            $hasForeignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', $databaseName)
                ->where('TABLE_NAME', 'messages')
                ->where('COLUMN_NAME', 'call_session_id')
                ->where('REFERENCED_TABLE_NAME', 'call_sessions')
                ->exists();

            Schema::table('messages', function (Blueprint $table) use ($hasForeignKey, $hasUniqueIndex) {
                if (! $hasUniqueIndex) {
                    $table->unique('call_session_id');
                }
                if (! $hasForeignKey) {
                    $table->foreign('call_session_id')->references('id')->on('call_sessions')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('messages')->where('message_type', 'call')->update([
            'message_type' => 'text',
            'body' => 'Voice call',
        ]);

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'call_session_id')) {
                $table->dropConstrainedForeignId('call_session_id');
            }
            $table->enum('message_type', [
                'voice',
                'image',
                'text',
                'video',
                'file',
                'sticker',
                'system_notification',
            ])->default('text')->change();
        });

        DB::table('call_sessions')->whereIn('status', ['ringing', 'active'])->update(['status' => 'cancelled']);
        DB::table('call_sessions')->whereIn('status', ['declined', 'ended', 'failed'])->update(['status' => 'completed']);

        Schema::table('call_sessions', function (Blueprint $table) {
            $columns = collect(['answered_at', 'expires_at', 'end_reason'])
                ->filter(fn (string $column): bool => Schema::hasColumn('call_sessions', $column))
                ->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
            $table->enum('status', [
                'ongoing',
                'completed',
                'missed',
                'cancelled',
            ])->default('ongoing')->change();
        });
    }
};
