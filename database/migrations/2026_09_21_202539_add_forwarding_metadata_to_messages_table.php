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
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('forwarded_from_message_id')->nullable()->after('reply_to_message_id')
                ->constrained('messages')->nullOnDelete();
            $table->foreignId('forwarded_from_sender_id')->nullable()->after('forwarded_from_message_id')
                ->constrained('users')->nullOnDelete();
            $table->string('forwarded_from_sender_name', 191)->nullable()->after('forwarded_from_sender_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forwarded_from_sender_id');
            $table->dropConstrainedForeignId('forwarded_from_message_id');
            $table->dropColumn('forwarded_from_sender_name');
        });
    }
};
