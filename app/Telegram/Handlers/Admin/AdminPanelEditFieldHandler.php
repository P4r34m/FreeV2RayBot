<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditPanelFieldConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the field-edit conversation (admin:panels:editfield:{combo}, combo = "{panelId}_{field}"). */
class AdminPanelEditFieldHandler
{
    public function __invoke(Nutgram $bot, string $combo): void
    {
        // Split on the FIRST underscore only — fields like base_url/api_token contain '_'.
        [$panelId, $field] = array_pad(explode('_', $combo, 2), 2, null);

        if (! is_numeric($panelId) || $field === null) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        Reply::toast($bot);

        /** @var EditPanelFieldConversation $conv */
        $conv = $bot->getContainer()->get(EditPanelFieldConversation::class);
        $conv->panelId = (int) $panelId;
        $conv->field = $field;
        $conv($bot);
    }
}
