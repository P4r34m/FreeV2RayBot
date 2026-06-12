<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Jobs\IssueConfigJob;
use App\Models\BotUser;
use App\Services\PanelSelector;
use App\Telegram\ChannelGate;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Issue a brand-new config (callback: config:new). */
class IssueNewHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        if (! ChannelGate::enforce($bot)) {
            return;
        }

        self::start($bot, $bot->get('botUser'));
    }

    /**
     * Begin a new-config issuance: enforce the active-config cap, then either
     * show the server picker (several panels) or issue immediately (one panel).
     * The user never picks a plan — its volume/duration come from the panel.
     *
     * Callers must have already answered the callback and passed the channel gate.
     */
    public static function start(Nutgram $bot, BotUser $user): void
    {
        $activeCount = $user->configs()->where('status', ConfigStatus::Active->value)->count();
        $max = $user->maxConfigs();

        if ($activeCount >= $max) {
            Reply::screen(
                $bot,
                Content::text('config.max_reached', ['max' => $max]),
                Keyboards::configMenu(true),
            );

            return;
        }

        $panels = app(PanelSelector::class)->available();

        if ($panels->isEmpty()) {
            Reply::screen(
                $bot,
                Content::text('config.no_panel', ['message' => 'در حال حاضر هیچ سروری در دسترس نیست.']),
                Keyboards::backMenu(),
            );

            return;
        }

        // Several servers → let the user choose which one their config comes from.
        if ($panels->count() > 1) {
            $kb = InlineKeyboardMarkup::make();
            foreach ($panels as $panel) {
                $kb->addRow(Btn::make('🖥 '.$panel->name, callback_data: 'config:new:'.$panel->id));
            }
            $kb->addRow(Keyboards::backButton(Keyboards::CB_GET_CONFIG));

            Reply::screen($bot, Content::text('config.pick_server'), $kb);

            return;
        }

        // Exactly one server → nothing to choose; issue on it right away.
        self::dispatch($bot, $user, $panels->first()->id);
    }

    /** Kick off issuance (panelId null => auto-select). */
    public static function dispatch(Nutgram $bot, BotUser $user, ?int $panelId = null): void
    {
        Reply::screen($bot, Content::text('config.creating'), Keyboards::backMenu());

        IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'new', panelId: $panelId);
    }
}
