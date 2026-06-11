<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra fields for the forward-to-add channel flow: private flag, the bot-made
 * invite link name, and a join counter attributed via chat_member updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('required_channels', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('username');
            $table->string('invite_link_name')->nullable()->after('invite_link');
            $table->unsignedBigInteger('join_count')->default(0)->after('invite_link_name');
            $table->unsignedBigInteger('member_count')->nullable()->after('join_count');
        });
    }

    public function down(): void
    {
        Schema::table('required_channels', function (Blueprint $table) {
            $table->dropColumn(['is_private', 'invite_link_name', 'join_count', 'member_count']);
        });
    }
};
