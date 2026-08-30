<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (['roles', 'users'] as $table) {
                $identifier = DB::connection()->getQueryGrammar()->wrapTable($table);
                DB::statement('ALTER TABLE '.$identifier.' ENGINE = InnoDB');
            }
        }

        // Preserve existing IDs, disabled/deleted roles and every legacy assignment.
        foreach (['super_admin', 'admin', 'teacher', 'student'] as $name) {
            $matches = DB::table('roles')->whereRaw('LOWER(name) = ?', [$name])->get();

            if ($matches->contains(fn ($role): bool => $role->name !== $name)
                || ($name === 'super_admin' && $matches->count() > 1)) {
                throw new RuntimeException("Ambiguous role {$name}; review the existing records before migrating.");
            }

            if ($matches->isEmpty()) {
                DB::table('roles')->insert([
                    'name' => $name,
                    'description' => 'Fixed institution role',
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Retain role records: rollback must never orphan assigned users.
    }
};
