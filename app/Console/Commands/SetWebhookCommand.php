<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

/**
 * Sets the Telegram webhook with the allowed_updates this bot needs — notably
 * chat_member (for force-join link analytics), which Telegram omits by default.
 * In production it also passes the secret_token that LaravelWebhook validates.
 */
class SetWebhookCommand extends Command
{
    protected $signature = 'bot:set-webhook {url? : Full webhook URL; defaults to APP_URL/webhook/SECRET}';

    protected $description = 'Set the Telegram webhook with the required allowed_updates';

    public function handle(Nutgram $bot): int
    {
        $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/')
            .'/webhook/'.config('v2raybot.bot.webhook_secret');

        $secret = config('nutgram.safe_mode', false) ? md5((string) config('app.key')) : null;

        $bot->setWebhook(
            url: $url,
            allowed_updates: [
                'message',
                'edited_message',
                'callback_query',
                'chat_member',
                'my_chat_member',
                'chat_join_request',
            ],
            drop_pending_updates: true,
            secret_token: $secret,
        );

        $this->info("Webhook set: {$url}");

        return self::SUCCESS;
    }
}
