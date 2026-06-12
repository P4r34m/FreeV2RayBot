<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\AddPlanConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Start the add-plan conversation (callback: admin:plans:add). */
class AdminPlanAddHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);
        AddPlanConversation::begin($bot);
    }
}
