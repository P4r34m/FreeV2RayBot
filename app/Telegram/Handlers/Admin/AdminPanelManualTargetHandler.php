<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditPanelTargetConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the manual target-entry conversation (admin:panels:tmanual:{id}). */
class AdminPanelManualTargetHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        /** @var EditPanelTargetConversation $conv */
        $conv = $bot->getContainer()->get(EditPanelTargetConversation::class);
        $conv->panelId = (int) $id;
        $conv($bot);
    }
}
