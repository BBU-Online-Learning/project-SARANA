<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (['school_classes', 'school_class_members', 'school_class_channels', 'school_class_channel_messages'] as $table) {
                DB::statement('ALTER TABLE '.DB::connection()->getQueryGrammar()->wrapTable($table).' ENGINE = InnoDB');
            }
        }

        Schema::create('class_membership_audits', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            // IDs are retained even if an account or class is eventually purged.
            $table->unsignedBigInteger('school_class_id');
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('target_id');
            $table->string('actor_role', 32);
            $table->string('action', 40);
            $table->string('old_role', 16)->nullable();
            $table->string('new_role', 16)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['school_class_id', 'created_at'], 'class_audit_time_idx');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Membership audit history must be retained; use a reviewed forward migration.');
    }
};
