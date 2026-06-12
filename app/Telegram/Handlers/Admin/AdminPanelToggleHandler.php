<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Panel;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Flip a panel's is_active flag then re-render its detail (callback: admin:panels:toggle:{id}). */
class AdminPanelToggleHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $panel = Panel::find((int) $id);

        if ($panel === null) {
            Reply::toast($bot, '❌ پنل یافت نشد', alert: true);
            (new AdminPanelsHandler)($bot);

            return;
        }

        $panel->update(['is_active' => ! $panel->is_active]);

        Reply::toast($bot, $panel->is_active ? '🟢 فعال شد' : '🔴 غیرفعال شد');

        AdminPanelViewHandler::render($bot, $panel);
    }
}
