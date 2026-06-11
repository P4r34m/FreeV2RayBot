<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a report event type to a forum-topic thread id inside the admin reports
 * group, so different events land in their own topic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_topics', function (Blueprint $table) {
            $table->id();
            $table->string('event')->unique(); // new_user|new_config|renew|referral|channel_join|blocked|error
            $table->string('title')->nullable();
            $table->unsignedBigInteger('thread_id')->nullable(); // message_thread_id (null = General)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_topics');
    }
};
