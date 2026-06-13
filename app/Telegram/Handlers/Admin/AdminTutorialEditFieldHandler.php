<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditTutorialFieldConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the tutorial field-edit conversation (admin:tutorials:edit:{combo} = "{id}_{field}"). */
class AdminTutorialEditFieldHandler
{
    public function __invoke(Nutgram $bot, string $combo): void
    {
        [$tutorialId, $field] = array_pad(explode('_', $combo, 2), 2, null);

        if (! is_numeric($tutorialId) || $field === null) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        Reply::toast($bot);

        /** @var EditTutorialFieldConversation $conv */
        $conv = $bot->getContainer()->get(EditTutorialFieldConversation::class);
        $conv->tutorialId = (int) $tutorialId;
        $conv->field = $field;
        $conv($bot);
    }
}
