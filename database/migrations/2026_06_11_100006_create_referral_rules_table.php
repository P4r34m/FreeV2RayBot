<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-defined referral rewards. "Per N invited users => give X traffic/days"
 * is a `recurring` rule with threshold=N; one-off bonuses are `milestone`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mode', 16)->default('recurring'); // App\Enums\ReferralRuleMode
            $table->unsignedInteger('threshold');             // number of invited users
            $table->string('reward_type', 16);                // App\Enums\RewardType
            $table->unsignedBigInteger('reward_amount');      // bytes (traffic) or days (duration)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rules');
    }
};
