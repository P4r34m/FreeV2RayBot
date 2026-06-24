<?php

namespace App\Telegram\Handlers;

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
        // Exactly ONE free config per user, for life. Once they have one (active OR
        // expired) they can only RENEW it — never get a brand-new free config.
        if ($user->freeConfig() !== null) {
            Reply::screen(
                $bot,
                Content::text('config.free_not_expired'),
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

        // Always show the server(s) — even a single one — with remaining capacity
        // in parentheses, and let the user pick.
        $kb = InlineKeyboardMarkup::make();
        foreach ($panels as $panel) {
            $remaining = $panel->remainingConfigs();
            // Show "(N ساب باقی‌مانده)"; for an unlimited panel show nothing at all.
            $label = $remaining === null
                ? $panel->name
                : $panel->name.' ('.$remaining.' ساب باقی‌مانده)';
            // Per-panel premium emoji (auto-detected from the panel name when the admin
            // sets it) wins; otherwise fall back to the shared "menu.server" icon/color.
            $kb->addRow(Btn::make(
                text: $label,
                callback_data: 'config:new:'.$panel->id,
                icon_custom_emoji_id: data_get($panel->settings, 'icon_emoji_id') ?: Content::iconEmojiId('menu.server'),
                style: Content::buttonStyle('menu.server'),
            ));
        }
        // Back goes to the MAIN MENU, not get_config — with no active config,
        // get_config IS this picker, so pointing back at it would loop.
        $kb->addRow(Keyboards::backButton(Keyboards::CB_MENU));

        Reply::screen($bot, Content::text('config.pick_server'), $kb);
    }

    /** Kick off issuance (panelId null => auto-select). */
    public static function dispatch(Nutgram $bot, BotUser $user, ?int $panelId = null): void
    {
        Reply::screen($bot, Content::text('config.creating'), Keyboards::backMenu());

        IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'new', panelId: $panelId);
    }
}
