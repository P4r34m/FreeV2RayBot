<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Tutorial;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle a tutorial active/inactive (callback: admin:tutorials:toggle:{id}). */
class AdminTutorialToggleHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $tutorial = Tutorial::find((int) $id);

        if (! $tutorial) {
            Reply::toast($bot, 'آموزش یافت نشد.', true);
            (new AdminTutorialsHandler)($bot);

            return;
        }

        $tutorial->is_active = ! $tutorial->is_active;
        $tutorial->save();

        Reply::toast($bot, $tutorial->is_active ? 'فعال شد.' : 'غیرفعال شد.');

        (new AdminTutorialViewHandler)($bot, (string) $tutorial->id);
    }
}
