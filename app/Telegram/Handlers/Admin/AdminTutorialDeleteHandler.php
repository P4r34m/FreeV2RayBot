<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Tutorial;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Delete a tutorial (callback: admin:tutorials:del:{id}). */
class AdminTutorialDeleteHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $tutorial = Tutorial::find((int) $id);

        if ($tutorial) {
            $tutorial->delete();
            Reply::toast($bot, 'آموزش حذف شد.');
        } else {
            Reply::toast($bot, 'آموزش یافت نشد.', true);
        }

        (new AdminTutorialsHandler)($bot);
    }
}
