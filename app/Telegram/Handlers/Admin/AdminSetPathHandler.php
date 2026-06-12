<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\SetPanelPathConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the set-panel-path conversation (callback: admin:setpath). */
class AdminSetPathHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);
        SetPanelPathConversation::begin($bot);
    }
}
