<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable inline-button labels. Telegram button text is plain Unicode,
 * but Bot API 9.4 allows a premium-emoji icon via icon_custom_emoji_id, stored
 * here so admins can decorate buttons with premium emoji.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_buttons', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('icon_custom_emoji_id')->nullable(); // premium emoji icon (Bot API 9.4+)
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_buttons');
    }
};
