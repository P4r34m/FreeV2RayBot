<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Temporary (anti-spam) blocks + strike counter for bot users. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->timestamp('blocked_until')->nullable()->after('is_blocked');
            $table->unsignedInteger('spam_strikes')->default(0)->after('blocked_until');
            $table->string('block_reason')->nullable()->after('spam_strikes');
        });
    }

    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropColumn(['blocked_until', 'spam_strikes', 'block_reason']);
        });
    }
};
