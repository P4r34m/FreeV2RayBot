<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable message texts (HTML, may embed <tg-emoji> premium emoji).
 * Resolved by key via App\Telegram\Content with placeholder substitution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('content');
            $table->string('group')->nullable();       // for grouping in admin
            $table->string('description')->nullable();  // hint + available placeholders
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_texts');
    }
};
