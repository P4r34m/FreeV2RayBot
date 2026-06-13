<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasable packages priced in coins (coin mode). Buying one either creates a
 * new config or tops up an existing one with this volume/duration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('data_limit_bytes')->default(0); // 0 = unlimited
            $table->unsignedInteger('duration_days')->default(0);       // 0 = no expiry
            $table->unsignedInteger('coin_price');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_plans');
    }
};
