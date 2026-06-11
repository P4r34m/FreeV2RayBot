<?php

namespace App\Telegram\Handlers\Admin;

use SergiX44\Nutgram\Nutgram;

/** Launch the block-user conversation (callback: admin:block). */
class AdminBlockHandler
{
    public function __invoke(Nutgram $bot): void
    {
        AdminUsersHandler::startBlock($bot, 'block');
    }
}
