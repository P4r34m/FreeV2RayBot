<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\BroadcastConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Start the broadcast conversation (callback: admin:broadcast). */
class AdminBroadcastHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);
        BroadcastConversation::begin($bot);
    }
}
