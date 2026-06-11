<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger: one row each time a referral rule pays out to a user, so
 * recurring/milestone rewards are never granted twice for the same threshold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_reward_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_user_id')->constrained('bot_users')->cascadeOnDelete();
            $table->foreignId('referral_rule_id')->nullable()->constrained('referral_rules')->nullOnDelete();
            $table->unsignedInteger('referral_count_at_grant');
            $table->string('reward_type', 16);
            $table->unsignedBigInteger('reward_amount');
            $table->string('note')->nullable();
            $table->timestamps();

            // A given rule pays a given user at most once per threshold checkpoint.
            $table->unique(['bot_user_id', 'referral_rule_id', 'referral_count_at_grant'], 'grant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_reward_grants');
    }
};
