<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Move a user main-menu button up/down (callback: admin:menumove:{combo} = "{dir}_{slug}"). */
class AdminMenuButtonMoveHandler
{
    public function __invoke(Nutgram $bot, string $combo): void
    {
        // dir is up|down (no underscore), so split on the first underscore only.
        [$dir, $slug] = array_pad(explode('_', $combo, 2), 2, null);

        if (! in_array($dir, ['up', 'down'], true) || ! isset(Keyboards::USER_BUTTONS[$slug])) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        $order = Keyboards::userButtonOrder();
        $i = array_search($slug, $order, true);
        $swap = $dir === 'up' ? $i - 1 : $i + 1;

        if ($swap < 0 || $swap >= count($order)) {
            Reply::toast($bot, 'امکان جابه‌جایی نیست');

            return;
        }

        [$order[$i], $order[$swap]] = [$order[$swap], $order[$i]];
        Setting::put(Keyboards::MENU_ORDER_KEY, array_values($order));

        Reply::toast($bot, '✅ جابه‌جا شد');
        (new AdminMenuButtonsHandler)($bot);
    }
}
