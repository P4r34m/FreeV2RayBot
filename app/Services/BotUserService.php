<?php

namespace App\Services;

use App\Models\BotUser;
use SergiX44\Nutgram\Nutgram;

/**
 * Provisions and updates BotUser rows from incoming Telegram updates, and
 * resolves the admin flag from the configured admin id list.
 */
class BotUserService
{
    /** Find-or-create the BotUser for the current Telegram sender. */
    public function resolve(Nutgram $bot): BotUser
    {
        $from = $bot->user();
        $telegramId = $from?->id ?? $bot->userId();

        $user = BotUser::firstOrNew(['telegram_id' => $telegramId]);

        $user->fill([
            'username' => $from?->username,
            'first_name' => $from?->first_name,
            'last_name' => $from?->last_name,
            'language_code' => $user->language_code ?: ($from?->language_code ?: 'fa'),
            'last_active_at' => now(),
        ]);

        // Always resolve to a definite bool (new records have no hydrated default).
        $user->is_admin = $this->isConfiguredAdmin($telegramId) || (bool) $user->is_admin;
        $user->is_blocked = (bool) $user->is_blocked;

        if (! $user->exists) {
            $user->last_started_at = now();
        }

        $user->save();

        return $user;
    }

    public function isConfiguredAdmin(int|string|null $telegramId): bool
    {
        if ($telegramId === null) {
            return false;
        }

        return in_array((string) $telegramId, config('v2raybot.bot.admin_ids', []), true);
    }
}
