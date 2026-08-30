<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret_encrypted')->nullable();
            $table->unsignedBigInteger('two_factor_last_used_step')->nullable();
            $table->unsignedBigInteger('auth_version')->default(0);
            $table->string('two_factor_recovery_token_hash', 64)->nullable();
            $table->unsignedBigInteger('recovery_requested_by')->nullable();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE password_reset_tokens ENGINE = InnoDB');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This security migration cannot be rolled back destructively. Restore an encrypted backup with its APP_KEY or use a reviewed forward migration.');
    }
};
