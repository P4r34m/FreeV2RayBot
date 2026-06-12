<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditTextConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the text-editing conversation (callback: admin:content:edittext). */
class AdminEditTextHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);
        EditTextConversation::begin($bot);
    }
}
