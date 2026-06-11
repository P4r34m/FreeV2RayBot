<?php

namespace App\Telegram\Handlers\Admin;

use SergiX44\Nutgram\Nutgram;

/** Launch the unblock-user conversation (callback: admin:unblock). */
class AdminUnblockHandler
{
    public function __invoke(Nutgram $bot): void
    {
        AdminUsersHandler::startBlock($bot, 'unblock');
    }
}
