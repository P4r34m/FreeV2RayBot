<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Models\Config;
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
            ->orderByRaw('CASE WHEN source = ? THEN 0 ELSE 1 END', [Config::SOURCE_FREE]) // free first
            ->latest()
            ->get();

        if ($configs->isEmpty()) {
            Reply::screen($bot, Content::text('account.no_config'), Keyboards::configMenu(false));

            return;
        }

        // One glass button per subscription, labelled by index + source tag.
        $kb = InlineKeyboardMarkup::make();
        $i = 0;
        foreach ($configs as $config) {
            $i++;
            $tag = $config->source === Config::SOURCE_COIN ? ' — سکه 🪙' : ' — رایگان';
            $kb->addRow(Btn::make(
                '🔑 اشتراک '.$i.' — '.$config->limitHuman().$tag,
                callback_data: 'config:view:'.$config->id,
            ));
        }
        $kb->addRow(Keyboards::backButton(Keyboards::CB_GET_CONFIG));

        Reply::screen($bot, Content::text('config.list_header'), $kb);
    }
}
