<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-button color/style (Telegram ButtonStyle: primary|success|danger; null = default). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_buttons', function (Blueprint $table) {
            $table->string('style', 16)->nullable()->after('icon_custom_emoji_id');
        });
    }

    public function down(): void
    {
        Schema::table('bot_buttons', function (Blueprint $table) {
            $table->dropColumn('style');
        });
    }
};
