<?php

namespace App\Telegram\Handlers\Admin;

use SergiX44\Nutgram\Nutgram;

/** Launch the per-user config-limit conversation (callback: admin:setlimit). */
class AdminSetUserLimitHandler
{
    public function __invoke(Nutgram $bot): void
    {
        AdminUsersHandler::startSetLimit($bot);
    }
}
