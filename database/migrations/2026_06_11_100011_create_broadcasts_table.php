<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin broadcast campaigns (پیام همگانی) with delivery progress, used by the
 * queued sender job and surfaced in the web panel's reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('message')->nullable();
            $table->string('media_type', 16)->nullable();
            $table->string('media_file_id')->nullable();
            $table->json('buttons')->nullable(); // optional inline buttons [{text,url}]
            $table->string('status', 16)->default('pending'); // pending|running|done|failed
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
