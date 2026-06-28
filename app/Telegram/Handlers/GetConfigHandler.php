<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Jobs\IssueConfigJob;
use App\Models\BotUser;
use App\Models\Config;
use App\Telegram\ChannelGate;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** "دریافت کانفیگ" — new / renew / seamless rebuild (callback: get_config). */
class GetConfigHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        if (! ChannelGate::enforce($bot)) {
            return;
        }

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        // Already has a live free config (active or expired)? Then only renew/status —
        // no brand-new free config.
        if ($user->freeConfig() !== null) {
            Reply::screen($bot, Content::text('config.menu_active'), Keyboards::configMenu(true));

            return;
        }

        // Had a free config that was removed from the panel (status Deleted)? Rebuild
        // it on the spot — same record/identifier, no "you have nothing"/picker steps.
        $removed = $user->configs()
            ->where('source', Config::SOURCE_FREE)
            ->where('status', ConfigStatus::Deleted->value)
            ->latest()
            ->first();

        if ($removed) {
            Reply::screen($bot, Content::text('config.creating'), Keyboards::backMenu());
            IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'renew', $removed->id);

            return;
        }

        // True first-timer → server picker.
        IssueNewHandler::start($bot, $user);
    }
}
