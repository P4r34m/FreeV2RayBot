<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditPanelSubConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the 3x-ui subscription settings conversation (admin:panels:sub:{id}). */
class AdminPanelSubHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        /** @var EditPanelSubConversation $conv */
        $conv = $bot->getContainer()->get(EditPanelSubConversation::class);
        $conv->panelId = (int) $id;
        $conv($bot);
    }
}
