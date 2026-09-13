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
        $hasDuplicates = DB::table('messages')
            ->whereNotNull('client_uuid')
            ->select('client_uuid')
            ->groupBy('client_uuid')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new \RuntimeException('Duplicate message client UUIDs must be resolved before enabling idempotent retries.');
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['client_uuid']);
            $table->unique('client_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->index('client_uuid');
        });
    }
};
