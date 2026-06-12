<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user override for the number of active configs a user may hold. NULL means
 * "use the global default" (v2raybot.limits.max_active_configs_per_user).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->unsignedInteger('max_configs')->nullable()->after('bonus_days');
        });
    }

    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropColumn('max_configs');
        });
    }
};
