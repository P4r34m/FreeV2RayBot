<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** List the user's active subscriptions as buttons (callback: config:status). */
class ConfigStatusHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        $configs = $user->configs()
            ->where('status', ConfigStatus::Active->value)
            ->with('panel')
            ->latest()
            ->get();

        if ($configs->isEmpty()) {
            Reply::screen($bot, Content::text('account.no_config'), Keyboards::configMenu(false));

            return;
        }

        // One glass button per subscription; tapping opens its detail view.
        $kb = InlineKeyboardMarkup::make();
        foreach ($configs as $config) {
            $kb->addRow(Btn::make(
                '🔑 '.($config->panel?->name ?? 'اشتراک').' — '.$config->limitHuman(),
                callback_data: 'config:view:'.$config->id,
            ));
        }
        $kb->addRow(Keyboards::backButton(Keyboards::CB_GET_CONFIG));

        Reply::screen($bot, Content::text('config.list_header'), $kb);
    }
}
