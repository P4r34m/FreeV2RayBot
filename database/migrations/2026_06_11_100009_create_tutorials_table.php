<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tutorial entries shown under the "آموزش‌ها" button, grouped by platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable(); // android|ios|windows|mac|...
            $table->longText('content');             // markdown/HTML for Telegram
            $table->string('media_type', 16)->nullable(); // photo|video|document
            $table->string('media_file_id')->nullable();  // cached Telegram file_id
            $table->string('url')->nullable();             // external link/button
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorials');
    }
};
