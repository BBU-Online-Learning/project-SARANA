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
        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->string('call_type', 10)->default('audio');
        });
        Schema::table('call_participants', function (Blueprint $table): void {
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->uuid('client_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_participants', function (Blueprint $table): void {
            $table->dropColumn(['last_seen_at', 'client_id']);
        });
        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->dropColumn('call_type');
        });
    }
};
