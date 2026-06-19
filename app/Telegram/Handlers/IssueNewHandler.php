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
        // Exactly ONE free config per user (not configurable). A still-running
        // free config blocks a new one until its time is up; coin configs don't count.
        $hasRunningFree = $user->configs()
            ->where('status', ConfigStatus::Active->value)
            ->where('source', \App\Models\Config::SOURCE_FREE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if ($hasRunningFree) {
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
            $suffix = $remaining === null ? 'نامحدود' : $remaining.' باقی‌مانده';
            $kb->addRow(Btn::make("🖥 {$panel->name} ({$suffix})", callback_data: 'config:new:'.$panel->id));
        }
        $kb->addRow(Keyboards::backButton(Keyboards::CB_GET_CONFIG));

        Reply::screen($bot, Content::text('config.pick_server'), $kb);
    }

    /** Kick off issuance (panelId null => auto-select). */
    public static function dispatch(Nutgram $bot, BotUser $user, ?int $panelId = null): void
    {
        Reply::screen($bot, Content::text('config.creating'), Keyboards::backMenu());

        IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'new', panelId: $panelId);
    }
}
