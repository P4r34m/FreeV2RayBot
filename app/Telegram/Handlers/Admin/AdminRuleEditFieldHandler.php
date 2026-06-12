<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\EditRuleFieldConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Launch the rule field-edit conversation (admin:rules:editfield:{combo}, combo = "{ruleId}_{field}"). */
class AdminRuleEditFieldHandler
{
    public function __invoke(Nutgram $bot, string $combo): void
    {
        [$ruleId, $field] = array_pad(explode('_', $combo, 2), 2, null);

        if (! is_numeric($ruleId) || $field === null) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        Reply::toast($bot);

        /** @var EditRuleFieldConversation $conv */
        $conv = $bot->getContainer()->get(EditRuleFieldConversation::class);
        $conv->ruleId = (int) $ruleId;
        $conv->field = $field;
        $conv($bot);
    }
}
