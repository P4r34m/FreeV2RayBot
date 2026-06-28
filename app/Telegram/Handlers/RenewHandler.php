<?php

namespace App\Telegram\Handlers;

use App\Jobs\IssueConfigJob;
use App\Models\BotUser;
use App\Models\Config;
use App\Panels\PanelManager;
use App\Telegram\ChannelGate;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Renew the user's free config. Allowed once it has expired — OR if its account is
 * gone from the panel (server re-pointed / panel deleted old users), in which case
 * the renew re-creates it (callback: config:renew).
 */
class RenewHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        if (! ChannelGate::enforce($bot)) {
            return;
        }

        /** @var BotUser $user */
        $user = $bot->get('botUser');
        // The free config to renew — active OR already expired (the expiry cron
        // flips it to Expired, but it's still the same config to renew).
        $config = $user->freeConfig();

        if (! $config) {
            // Nothing to renew — they never had a free config, or it was removed
            // (e.g. the panel deleted it). Don't dead-end them: set up a NEW one.
            IssueNewHandler::start($bot, $user);

            return;
        }

        // Block renew only while the config is genuinely still usable: not expired
        // AND still present on the panel. If the account is gone from the panel, let
        // the renew through — it re-creates it (so users aren't stuck "not expired"
        // on a config that no longer exists on the server).
        if (! $config->isExpired() && $this->existsOnPanel($config)) {
            Reply::screen(
                $bot,
                Content::text('config.renew_not_expired'),
                Keyboards::configMenu(true),
            );

            return;
        }

        Reply::screen(
            $bot,
            Content::text('config.renewing'),
            Keyboards::backMenu(),
        );

        IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'renew', $config->id);
    }

    /** Whether the config's account still exists on its panel right now. */
    private function existsOnPanel(Config $config): bool
    {
        $config->loadMissing('panel');

        if (! $config->panel) {
            return false; // no panel to renew against → allow re-provision
        }

        try {
            return app(PanelManager::class)->driver($config->panel)->getUsage($config->remote_identifier) !== null;
        } catch (Throwable) {
            return true; // panel unreachable → assume it exists (don't bypass on a transient error)
        }
    }
}
