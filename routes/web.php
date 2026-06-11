<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Telegram webhook (cacheable controller route; CSRF-exempt — see bootstrap/app.php).
Route::post('/webhook/{token}', TelegramWebhookController::class)->name('telegram.webhook');
