<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** The user's recent config history (callback: profile:history). */
class ProfileHistoryHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        /** @var BotUser $user */
        $user = $bot->get('botUser');
        $configs = $user->configs()->with(['panel', 'plan'])->latest()->limit(10)->get();

        $lines = [Content::text('profile.history_header'), ''];

        if ($configs->isEmpty()) {
            $lines[] = Content::text('profile.history_empty');
        } else {
            foreach ($configs as $config) {
                $lines[] = sprintf(
                    '• %s | %s | %s | %s',
                    $config->created_at?->format('Y-m-d') ?? '-',
                    $config->status->label(),
                    $config->limitHuman(),
                    $config->panel?->name ?? '—',
                );

                // Surface the link for active subscriptions so the user can grab
                // all of them from here, not just the latest.
                if ($config->status === ConfigStatus::Active && $config->subscription_url) {
                    $lines[] = '<code>'.htmlspecialchars($config->subscription_url, ENT_QUOTES).'</code>';
                }
            }
        }

        Reply::screen($bot, implode("\n", $lines), Keyboards::single('common.back', Keyboards::CB_PROFILE));
    }
}
