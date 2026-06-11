<?php

namespace App\Telegram\Handlers;

use App\Models\RequiredChannel;
use SergiX44\Nutgram\Nutgram;

/**
 * Attributes channel joins to the invite link they came through, for force-join
 * link analytics. Registered OUTSIDE the user-middleware group (channel joiners
 * are not bot users). Requires 'chat_member' in the webhook allowed_updates.
 */
class ChatMemberHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $update = $bot->chatMember();
        if (! $update) {
            return;
        }

        $new = $update->new_chat_member?->status?->value;
        $old = $update->old_chat_member?->status?->value;

        $joinedNow = in_array($new, ['member', 'administrator', 'creator'], true)
            && in_array($old, ['left', 'kicked'], true);

        $link = $update->invite_link?->invite_link;

        if ($joinedNow && $link) {
            RequiredChannel::where('invite_link', $link)->increment('join_count');
        }
    }
}
