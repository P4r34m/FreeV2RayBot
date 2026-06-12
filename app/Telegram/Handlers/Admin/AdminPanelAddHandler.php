<?php

namespace App\Telegram\Handlers\Admin;

use SergiX44\Nutgram\Nutgram;

/** Start the add-panel conversation (callback: admin:panels:add). */
class AdminPanelAddHandler
{
    public function __invoke(Nutgram $bot): void
    {
        AdminPanelsHandler::startAdd($bot);
    }
}
