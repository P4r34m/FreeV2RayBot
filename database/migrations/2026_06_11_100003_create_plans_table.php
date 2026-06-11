<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The volume/duration package a user receives. Admin sets the data limit and
 * duration here; one plan is flagged default for new issuances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('data_limit_bytes')->default(0); // 0 = unlimited
            $table->unsignedInteger('duration_days')->default(0);       // 0 = unlimited
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // Restrict the plan to a single panel, or null to allow any active panel.
            $table->foreignId('panel_id')->nullable()->constrained('panels')->nullOnDelete();

            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
