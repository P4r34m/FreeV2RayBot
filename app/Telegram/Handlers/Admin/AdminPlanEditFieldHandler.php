<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditPlanFieldConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the plan field-edit conversation (admin:plans:editfield:{combo}, combo = "{planId}_{field}"). */
class AdminPlanEditFieldHandler
{
    public function __invoke(Nutgram $bot, string $combo): void
    {
        [$planId, $field] = array_pad(explode('_', $combo, 2), 2, null);

        if (! is_numeric($planId) || $field === null) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        Reply::toast($bot);

        /** @var EditPlanFieldConversation $conv */
        $conv = $bot->getContainer()->get(EditPlanFieldConversation::class);
        $conv->planId = (int) $planId;
        $conv->field = $field;
        $conv($bot);
    }
}
