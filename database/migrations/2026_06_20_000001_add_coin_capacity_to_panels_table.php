<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separate the per-panel limit for COIN configs from the FREE one: `capacity` now
 * caps free configs and `coin_capacity` caps coin-purchased ones (null = unlimited).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            $table->unsignedInteger('coin_capacity')->nullable()->after('capacity'); // max coin configs, null = unlimited
        });
    }

    public function down(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            $table->dropColumn('coin_capacity');
        });
    }
};
