<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram end-users of the bot (distinct from the `users` table, which holds
 * web/Filament admins).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('language_code', 8)->default('fa');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_blocked')->default(false);

            // Referral wiring (self reference).
            $table->foreignId('referred_by')->nullable()
                ->constrained('bot_users')->nullOnDelete();
            $table->unsignedInteger('referral_count')->default(0); // verified referrals
            $table->unsignedInteger('referral_rewarded_count')->default(0); // consumed by recurring rules

            // Referral reward wallet, applied at issuance/renewal time.
            $table->unsignedBigInteger('bonus_traffic_bytes')->default(0);
            $table->unsignedInteger('bonus_days')->default(0);

            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index('referred_by');
            $table->index('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_users');
    }
};
