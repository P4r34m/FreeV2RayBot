<?php

namespace App\Services;

use App\Models\RequiredChannel;
use RuntimeException;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\Message\Message;
use SergiX44\Nutgram\Telegram\Types\Message\MessageOriginChannel;
use Throwable;

/**
 * Builds a required channel from a forwarded message: extracts the source
 * channel id, then (if the bot is admin) mints a dedicated invite link whose
 * joins can be attributed for stats.
 */
class ChannelService
{
    public function channelFromMessage(?Message $message): ?Chat
    {
        if (! $message) {
            return null;
        }

        $origin = $message->forward_origin;
        if ($origin instanceof MessageOriginChannel) {
            return $origin->chat;
        }

        return $message->forward_from_chat;
    }

    /**
     * Register (or update) a required channel from the forwarded message.
     *
     * @throws RuntimeException when the message isn't from a channel
     */
    public function addFromForward(Nutgram $bot, ?Message $message): RequiredChannel
    {
        $chat = $this->channelFromMessage($message);

        // isChannel() is a safe strict comparison (Chat::$type is ChatType|string).
        if (! $chat || ! $chat->isChannel()) {
            throw new RuntimeException('پیام باید از یک کانال فوروارد شود.');
        }

        $username = $chat->username;
        $isPrivate = $username === null;

        [$inviteLink, $linkName] = $this->makeInviteLink($bot, $chat);

        return RequiredChannel::updateOrCreate(
            ['chat_id' => (string) $chat->id],
            [
                'title' => $chat->title ?? 'کانال',
                'username' => $username,
                'is_private' => $isPrivate,
                'invite_link' => $inviteLink ?? ($username ? 'https://t.me/'.$username : null),
                'invite_link_name' => $linkName,
                'is_active' => true,
            ],
        );
    }

    /** @return array{0: ?string, 1: ?string} [inviteLink, name] */
    protected function makeInviteLink(Nutgram $bot, Chat $chat): array
    {
        $name = 'FreeV2RayBot-'.substr((string) abs($chat->id), -6);

        try {
            $link = $bot->createChatInviteLink(chat_id: $chat->id, name: $name);

            return [$link?->invite_link, $name];
        } catch (Throwable) {
            // Bot is not admin / lacks can_invite_users. Public channels still work
            // via the username link; private channels will need the bot promoted.
            return [null, null];
        }
    }

    /** Refresh the cached member count for a channel. */
    public function syncMemberCount(Nutgram $bot, RequiredChannel $channel): void
    {
        try {
            $channel->update(['member_count' => $bot->getChatMemberCount($channel->chat_id)]);
        } catch (Throwable) {
            // ignore
        }
    }
}
