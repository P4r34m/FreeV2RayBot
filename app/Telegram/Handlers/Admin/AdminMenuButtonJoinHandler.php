<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle whether a user button shares the previous button's row (admin:menujoin:{slug}). */
class AdminMenuButtonJoinHandler
{
    public function __invoke(Nutgram $bot, string $slug): void
    {
        if (! isset(Keyboards::USER_BUTTONS[$slug])) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        $joined = Setting::get(Keyboards::MENU_JOINED_KEY, []);
        $joined = is_array($joined) ? $joined : [];

        if (in_array($slug, $joined, true)) {
            $joined = array_values(array_diff($joined, [$slug]));
            Reply::toast($bot, '▫️ سطر جدا');
        } else {
            $joined[] = $slug;
            Reply::toast($bot, '🔗 هم‌ردیف با قبلی');
        }

        Setting::put(Keyboards::MENU_JOINED_KEY, $joined);

        (new AdminMenuButtonsHandler)($bot);
    }
}
