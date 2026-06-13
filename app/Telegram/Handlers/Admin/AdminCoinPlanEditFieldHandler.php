<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditCoinPlanFieldConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the coin-plan field-edit conversation (admin:coinplans:edit:{combo} = "{id}_{field}"). */
class AdminCoinPlanEditFieldHandler
{
    public function __invoke(Nutgram $bot, string $combo): void
    {
        [$planId, $field] = array_pad(explode('_', $combo, 2), 2, null);

        if (! is_numeric($planId) || $field === null) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        Reply::toast($bot);

        /** @var EditCoinPlanFieldConversation $conv */
        $conv = $bot->getContainer()->get(EditCoinPlanFieldConversation::class);
        $conv->planId = (int) $planId;
        $conv->field = $field;
        $conv($bot);
    }
}
