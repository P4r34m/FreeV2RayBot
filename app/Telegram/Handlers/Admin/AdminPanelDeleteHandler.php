<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Panel;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Delete a panel then return to the list (callback: admin:panels:del:{id}). */
class AdminPanelDeleteHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $panel = Panel::find((int) $id);

        if ($panel === null) {
            Reply::toast($bot, '❌ پنل یافت نشد', alert: true);
            (new AdminPanelsHandler)($bot);

            return;
        }

        $name = $panel->name;
        $panel->delete();

        Reply::toast($bot, "🗑 پنل «{$name}» حذف شد");

        (new AdminPanelsHandler)($bot);
    }
}
