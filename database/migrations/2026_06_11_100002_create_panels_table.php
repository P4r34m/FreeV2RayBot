<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V2Ray panels (servers) the admin registers. `type` selects the driver and
 * `settings` holds per-type config (inbound id, subscription host/port/path,
 * squad uuids, group ids, ...). Credentials are encrypted at the model level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32); // App\Enums\PanelType
            $table->string('base_url');
            $table->text('username')->nullable();   // encrypted
            $table->text('password')->nullable();   // encrypted
            $table->text('api_token')->nullable();   // encrypted (Remnawave / cached)
            $table->json('settings')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0); // higher = preferred
            $table->unsignedInteger('capacity')->nullable();  // max configs, null = unlimited
            $table->unsignedInteger('active_config_count')->default(0);

            $table->timestamp('last_health_check_at')->nullable();
            $table->string('health_status', 16)->nullable(); // ok|failed
            $table->text('health_message')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panels');
    }
};
