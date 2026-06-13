<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Flip a user main-menu button's visibility (callback: admin:menubtn:{slug}). */
class AdminMenuButtonToggleHandler
{
    public function __invoke(Nutgram $bot, string $slug): void
    {
        $entry = Keyboards::USER_BUTTONS[$slug] ?? null;

        if ($entry === null) {
            Reply::toast($bot, 'دکمه نامعتبر', alert: true);

            return;
        }

        $key = 'menu_visible:'.$entry[0];
        $now = ! Setting::bool($key, true);
        Setting::put($key, $now);

        Reply::toast($bot, $now ? '✅ نمایش داده می‌شود' : '🙈 پنهان شد');

        // Re-render the submenu with refreshed states.
        (new AdminMenuButtonsHandler)($bot);
    }
}
