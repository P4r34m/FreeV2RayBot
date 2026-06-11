<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\SetReportsGroupConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Start the reports-group setup conversation (callback: admin:setgroup). */
class AdminSetGroupHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);
        SetReportsGroupConversation::begin($bot);
    }
}
