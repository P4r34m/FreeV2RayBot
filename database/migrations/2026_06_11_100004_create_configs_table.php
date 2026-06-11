<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An issued config = a client/account created on a panel for a bot user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_user_id')->constrained('bot_users')->cascadeOnDelete();
            $table->foreignId('panel_id')->nullable()->constrained('panels')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();

            $table->string('remote_identifier'); // email/username on the panel
            $table->string('remote_uuid')->nullable(); // vless client id (3x-ui)
            $table->string('sub_id')->nullable();       // 3x-ui subId
            $table->text('subscription_url')->nullable();
            $table->json('config_links')->nullable();   // raw vless/vmess links

            $table->unsignedBigInteger('data_limit_bytes')->default(0);
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->timestamp('expires_at')->nullable();

            $table->string('status', 16)->default('active'); // App\Enums\ConfigStatus
            $table->timestamp('last_synced_at')->nullable();
            $table->json('panel_response')->nullable();
            $table->timestamps();

            $table->index(['bot_user_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
            $table->unique(['panel_id', 'remote_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configs');
    }
};
