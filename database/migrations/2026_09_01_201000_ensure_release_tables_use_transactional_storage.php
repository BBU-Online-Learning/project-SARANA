<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        /** @var list<array{table: string, column: string, parent: string, name: string, delete: string}> $foreignKeys */
        $foreignKeys = [
            ['table' => 'users', 'column' => 'role_id', 'parent' => 'roles', 'name' => 'users_role_fk', 'delete' => 'RESTRICT'],
            ['table' => 'sessions', 'column' => 'user_id', 'parent' => 'users', 'name' => 'sessions_user_fk', 'delete' => 'CASCADE'],
            ['table' => 'chat_rooms', 'column' => 'created_by', 'parent' => 'users', 'name' => 'rooms_creator_fk', 'delete' => 'SET NULL'],
            ['table' => 'chat_room_members', 'column' => 'room_id', 'parent' => 'chat_rooms', 'name' => 'room_members_room_fk', 'delete' => 'CASCADE'],
            ['table' => 'chat_room_members', 'column' => 'user_id', 'parent' => 'users', 'name' => 'room_members_user_fk', 'delete' => 'CASCADE'],
            ['table' => 'messages', 'column' => 'room_id', 'parent' => 'chat_rooms', 'name' => 'messages_room_fk', 'delete' => 'CASCADE'],
            ['table' => 'messages', 'column' => 'sender_id', 'parent' => 'users', 'name' => 'messages_sender_fk', 'delete' => 'CASCADE'],
            ['table' => 'messages', 'column' => 'reply_to_message_id', 'parent' => 'messages', 'name' => 'messages_reply_fk', 'delete' => 'SET NULL'],
            ['table' => 'message_reads', 'column' => 'message_id', 'parent' => 'messages', 'name' => 'message_reads_message_fk', 'delete' => 'CASCADE'],
            ['table' => 'message_reads', 'column' => 'user_id', 'parent' => 'users', 'name' => 'message_reads_user_fk', 'delete' => 'CASCADE'],
            ['table' => 'call_sessions', 'column' => 'room_id', 'parent' => 'chat_rooms', 'name' => 'call_sessions_room_fk', 'delete' => 'CASCADE'],
            ['table' => 'call_sessions', 'column' => 'initiated_by', 'parent' => 'users', 'name' => 'call_sessions_initiator_fk', 'delete' => 'CASCADE'],
            ['table' => 'call_participants', 'column' => 'call_session_id', 'parent' => 'call_sessions', 'name' => 'call_participants_call_fk', 'delete' => 'CASCADE'],
            ['table' => 'call_participants', 'column' => 'user_id', 'parent' => 'users', 'name' => 'call_participants_user_fk', 'delete' => 'CASCADE'],
            ['table' => 'attachments', 'column' => 'message_id', 'parent' => 'messages', 'name' => 'attachments_message_fk', 'delete' => 'CASCADE'],
            ['table' => 'attachments', 'column' => 'room_id', 'parent' => 'chat_rooms', 'name' => 'attachments_room_fk', 'delete' => 'CASCADE'],
            ['table' => 'attachments', 'column' => 'uploaded_by', 'parent' => 'users', 'name' => 'attachments_uploader_fk', 'delete' => 'CASCADE'],
            ['table' => 'message_user_deletions', 'column' => 'message_id', 'parent' => 'messages', 'name' => 'message_deletions_message_fk', 'delete' => 'CASCADE'],
            ['table' => 'message_user_deletions', 'column' => 'user_id', 'parent' => 'users', 'name' => 'message_deletions_user_fk', 'delete' => 'CASCADE'],
            ['table' => 'message_reactions', 'column' => 'message_id', 'parent' => 'messages', 'name' => 'message_reactions_message_fk', 'delete' => 'CASCADE'],
            ['table' => 'message_reactions', 'column' => 'user_id', 'parent' => 'users', 'name' => 'message_reactions_user_fk', 'delete' => 'CASCADE'],
            ['table' => 'school_classes', 'column' => 'created_by', 'parent' => 'users', 'name' => 'school_classes_creator_fk', 'delete' => 'SET NULL'],
            ['table' => 'school_class_members', 'column' => 'school_class_id', 'parent' => 'school_classes', 'name' => 'class_members_class_fk', 'delete' => 'CASCADE'],
            ['table' => 'school_class_members', 'column' => 'user_id', 'parent' => 'users', 'name' => 'class_members_user_fk', 'delete' => 'CASCADE'],
            ['table' => 'school_class_channels', 'column' => 'school_class_id', 'parent' => 'school_classes', 'name' => 'class_channels_class_fk', 'delete' => 'CASCADE'],
            ['table' => 'school_class_channels', 'column' => 'created_by', 'parent' => 'users', 'name' => 'class_channels_creator_fk', 'delete' => 'SET NULL'],
            ['table' => 'school_class_channel_messages', 'column' => 'school_class_channel_id', 'parent' => 'school_class_channels', 'name' => 'class_messages_channel_fk', 'delete' => 'CASCADE'],
            ['table' => 'school_class_channel_messages', 'column' => 'sender_id', 'parent' => 'users', 'name' => 'class_messages_sender_fk', 'delete' => 'CASCADE'],
        ];

        foreach ($foreignKeys as $foreignKey) {
            if (! Schema::hasTable($foreignKey['table']) || ! Schema::hasTable($foreignKey['parent'])
                || ! Schema::hasColumn($foreignKey['table'], $foreignKey['column'])) {
                continue;
            }

            $orphansExist = DB::table($foreignKey['table'].' as child')
                ->leftJoin($foreignKey['parent'].' as parent', 'child.'.$foreignKey['column'], '=', 'parent.id')
                ->whereNotNull('child.'.$foreignKey['column'])
                ->whereNull('parent.id')
                ->exists();

            if ($orphansExist) {
                throw new RuntimeException("Cannot secure {$foreignKey['table']}.{$foreignKey['column']}: orphaned rows require reviewed repair.");
            }
        }

        $tables = [
            'users',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'roles',
            'chat_rooms',
            'chat_room_members',
            'messages',
            'message_reads',
            'call_sessions',
            'call_participants',
            'attachments',
            'message_user_deletions',
            'message_reactions',
            'media',
            'school_classes',
            'school_class_members',
            'school_class_channels',
            'school_class_channel_messages',
            'class_membership_audits',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $identifier = DB::connection()->getQueryGrammar()->wrapTable($table);
                DB::statement('ALTER TABLE '.$identifier.' ENGINE = InnoDB');
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            if (! Schema::hasTable($foreignKey['table']) || ! Schema::hasTable($foreignKey['parent'])
                || ! Schema::hasColumn($foreignKey['table'], $foreignKey['column'])) {
                continue;
            }

            $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $foreignKey['table'])
                ->where('COLUMN_NAME', $foreignKey['column'])
                ->where('REFERENCED_TABLE_NAME', $foreignKey['parent'])
                ->where('REFERENCED_COLUMN_NAME', 'id')
                ->exists();

            if (! $exists) {
                $grammar = DB::connection()->getQueryGrammar();
                $table = $grammar->wrapTable($foreignKey['table']);
                $column = $grammar->wrap($foreignKey['column']);
                $parent = $grammar->wrapTable($foreignKey['parent']);
                $name = $grammar->wrap($foreignKey['name']);

                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY ({$column}) REFERENCES {$parent} (`id`) ON DELETE {$foreignKey['delete']}");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Transactional storage is intentionally retained to protect history and concurrent writes.
    }
};
