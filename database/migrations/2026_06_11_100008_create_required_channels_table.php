<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Channels/groups a user must join before using the bot (force-join).
 * The global on/off switch lives in `settings.channel_lock_enabled`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('required_channels', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('chat_id');            // -100... numeric id or @username (for getChatMember)
            $table->string('username')->nullable(); // @handle (display)
            $table->string('invite_link')->nullable(); // join URL (private channels)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('required_channels');
    }
};
