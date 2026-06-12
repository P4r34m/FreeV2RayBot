<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditButtonConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the button-editing conversation (callback: admin:content:editbtn). */
class AdminEditButtonHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);
        EditButtonConversation::begin($bot);
    }
}
