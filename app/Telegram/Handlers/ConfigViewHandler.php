<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Models\Config;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Presenter;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Show one subscription's details + actions (callback: config:view:{id}). */
class ConfigViewHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        // Scope by the user's own configs so one user can't view another's.
        $config = $user->configs()->whereKey((int) $id)->with(['panel', 'plan'])->first();

        // Pull fresh usage/remaining/expiry straight from the panel at view time.
        if ($config) {
            $config = app(\App\Services\ConfigUsageService::class)->refresh($config);
        }

        self::render($bot, $config);
    }

    /** Render a config's detail screen (no callback ack — callers handle that). */
    public static function render(Nutgram $bot, ?Config $config): void
    {
        if (! $config) {
            Reply::screen($bot, '⚠️ این اشتراک پیدا نشد.', Keyboards::single('common.back', Keyboards::CB_CONFIG_STATUS));

            return;
        }

        $kb = InlineKeyboardMarkup::make();

        if ($config->subscription_url) {
            $kb->addRow(Content::button('config.view_sub_site', url: $config->subscription_url));
            $kb->addRow(Content::button('config.single_configs', 'config:links:'.$config->id));
        }

        $kb->addRow(Content::button('config.rotate', 'config:rotate:'.$config->id))
            ->addRow(Btn::make('🔙 بازگشت به لیست', callback_data: Keyboards::CB_CONFIG_STATUS));

        Reply::screen($bot, Presenter::accountStatus($config), $kb);
    }
}
