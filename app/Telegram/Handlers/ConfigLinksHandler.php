<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Services\ConfigDeliveryService;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Show a config's individual protocol links (callback: config:links:{id}). */
class ConfigLinksHandler
{
    /** Cap rendered links so the message stays under Telegram's 4096 limit. */
    private const MAX = 20;

    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot, '⏳ در حال دریافت کانفیگ‌ها...');

        /** @var BotUser $user */
        $user = $bot->get('botUser');
        $config = $user->configs()->whereKey((int) $id)->first();

        if (! $config) {
            Reply::screen($bot, '⚠️ این اشتراک پیدا نشد.', Keyboards::single('common.back', Keyboards::CB_CONFIG_STATUS));

            return;
        }

        $links = app(ConfigDeliveryService::class)->fetchLinks($config);
        $back = Keyboards::single('common.back', 'config:view:'.$config->id);

        if ($links === []) {
            Reply::screen($bot, Content::text('config.single_empty'), $back);

            return;
        }

        $shown = array_slice($links, 0, self::MAX);
        $rendered = collect($shown)
            ->map(fn (string $l) => '<code>'.htmlspecialchars($l, ENT_QUOTES).'</code>')
            ->implode("\n\n");

        if (count($links) > count($shown)) {
            $rendered .= "\n\n➕ و ".(count($links) - count($shown)).' کانفیگ دیگر (از لینک اشتراک بگیرید).';
        }

        Reply::screen($bot, Content::text('config.single_header', ['links' => $rendered]), $back);
    }
}
