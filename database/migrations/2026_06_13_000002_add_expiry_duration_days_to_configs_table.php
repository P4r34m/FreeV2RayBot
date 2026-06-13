<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The on-hold duration (days from first use) captured at issuance, so the expiry
 * label stays correct even if the plan is later edited or deleted. NULL for
 * configs that have an absolute expiry or are genuinely unlimited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->unsignedInteger('expiry_duration_days')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->dropColumn('expiry_duration_days');
        });
    }
};
