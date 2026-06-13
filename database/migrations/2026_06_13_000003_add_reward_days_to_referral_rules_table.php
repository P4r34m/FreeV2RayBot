<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Second reward dimension for combined ("both") referral rules: reward_amount
 * holds the traffic (bytes) and reward_days holds the time (days). NULL for the
 * single-dimension traffic/duration rule types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_rules', function (Blueprint $table) {
            $table->unsignedInteger('reward_days')->nullable()->after('reward_amount');
        });
    }

    public function down(): void
    {
        Schema::table('referral_rules', function (Blueprint $table) {
            $table->dropColumn('reward_days');
        });
    }
};
