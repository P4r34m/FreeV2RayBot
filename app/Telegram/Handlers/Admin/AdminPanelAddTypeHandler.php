<?php

namespace App\Telegram\Handlers\Admin;

use App\Enums\PanelType;
use App\Telegram\Conversations\AddPanelConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Step 2: type chosen, launch the add-panel conversation (admin:panels:addtype:{type}). */
class AdminPanelAddTypeHandler
{
    public function __invoke(Nutgram $bot, string $type): void
    {
        $panelType = PanelType::tryFrom($type);

        if ($panelType === null) {
            Reply::toast($bot, 'نوع نامعتبر', alert: true);

            return;
        }

        Reply::toast($bot);

        /** @var AddPanelConversation $conv */
        $conv = $bot->getContainer()->get(AddPanelConversation::class);
        $conv->type = $panelType;
        $conv($bot);
    }
}
