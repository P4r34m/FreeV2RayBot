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

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        $activeCount = $user->configs()->where('status', ConfigStatus::Active->value)->count();
        $max = (int) config('v2raybot.limits.max_active_configs_per_user', 1);

        if ($activeCount >= $max) {
            Reply::screen(
                $bot,
                Content::text('config.max_reached', ['max' => $max]),
                Keyboards::configMenu(true),
            );

            return;
        }

        // If more than one server is available, let the user choose; otherwise
        // auto-select. The plan (volume/duration) is resolved from the panel.
        $panels = app(PanelSelector::class)->available();

        if ($panels->count() > 1) {
            $kb = InlineKeyboardMarkup::make();
            foreach ($panels as $panel) {
                $kb->addRow(Btn::make('🖥 '.$panel->name, callback_data: 'config:new:'.$panel->id));
            }
            $kb->addRow(Keyboards::backButton(Keyboards::CB_GET_CONFIG));

            Reply::screen($bot, '🌐 سرور موردنظر را انتخاب کنید:', $kb);

            return;
        }

        $this->dispatch($bot, $user);
    }

    /** Kick off issuance (panelId null => auto-select). */
    public static function dispatch(Nutgram $bot, BotUser $user, ?int $panelId = null): void
    {
        Reply::screen($bot, Content::text('config.creating'), Keyboards::backMenu());

        IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'new', panelId: $panelId);
    }
}
