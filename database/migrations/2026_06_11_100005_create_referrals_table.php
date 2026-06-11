<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per invited user. Becomes `verified` once the invitee performs the
 * qualifying action (configurable; default = receives their first config),
 * which is when it counts toward the referrer's reward thresholds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('bot_users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('bot_users')->cascadeOnDelete();
            $table->string('status', 16)->default('pending'); // pending|verified
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
