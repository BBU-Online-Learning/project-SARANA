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
        Schema::table('messages', function (Blueprint $table) {
            $table->enum('message_type', [
                'voice',
                'image',
                'text',
                'video',
                'file',
                'sticker',
                'system_notification',
            ])->default('text')->change();
            $table->string('sticker_id', 64)->nullable()->after('body')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('messages')->where('message_type', 'sticker')->exists()) {
            throw new \RuntimeException('Delete or migrate sticker messages before removing sticker support.');
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sticker_id']);
            $table->dropColumn('sticker_id');
            $table->enum('message_type', [
                'voice',
                'image',
                'text',
                'video',
                'file',
                'system_notification',
            ])->default('text')->change();
        });
    }
};
