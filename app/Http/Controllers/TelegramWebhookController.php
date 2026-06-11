<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SergiX44\Nutgram\Nutgram;

/**
 * Telegram webhook entry point. The {token} path segment is a shared secret;
 * in production Nutgram additionally verifies the X-Telegram-Bot-Api-Secret-Token
 * header (safe_mode). Kept as a controller (not a closure) so route:cache works.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, Nutgram $bot, string $token): Response
    {
        abort_unless(
            hash_equals((string) config('v2raybot.bot.webhook_secret'), $token),
            404
        );

        $bot->run();

        return response()->noContent();
    }
}
