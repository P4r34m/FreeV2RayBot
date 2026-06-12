<?php

namespace App\Telegram\Middleware;

use App\Models\BotUser;
use App\Models\Setting;
use App\Services\BotUserService;
use App\Support\SettingKey;
use App\Telegram\Content;
use SergiX44\Nutgram\Nutgram;

/**
 * Master on/off switch: when the bot is disabled, only admins may use it (so an
 * admin can always switch it back on via /on or the admin menu).
 *
 * Admin status is resolved here directly (configured ids + DB), NOT solely from
 * $bot->get('botUser') — so re-enabling never depends on middleware ordering.
 */
class BotEnabledGuard
{
    public function __construct(private readonly BotUserService $users) {}

    public function __invoke(Nutgram $bot, $next): void
    {
        if (Setting::bool(SettingKey::BOT_ENABLED, true) || $this->isAdmin($bot)) {
            $next($bot);

            return;
        }

        $bot->sendMessage(Content::text('bot.disabled'));
    }

    private function isAdmin(Nutgram $bot): bool
    {
        $user = $bot->get('botUser');
        if ($user instanceof BotUser) {
            return $user->is_admin;
        }

        $telegramId = $bot->userId();
        if ($telegramId === null) {
            return false;
        }

        return $this->users->isConfiguredAdmin($telegramId)
            || BotUser::where('telegram_id', $telegramId)->where('is_admin', true)->exists();
    }
}
