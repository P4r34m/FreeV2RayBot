<?php

namespace App\Telegram\Handlers\Admin;

use SergiX44\Nutgram\Nutgram;

/** Start the add-channel conversation (callback: admin:addchannel). */
class AdminAddChannelHandler
{
    public function __invoke(Nutgram $bot): void
    {
        AdminChannelsHandler::startAdd($bot);
    }
}
