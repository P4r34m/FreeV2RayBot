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
 * Resolves a channel from a forwarded message. The admin supplies the invite
 * link themselves (the bot does NOT create one); join analytics still work
 * because chat_member updates report whichever link a user joined through.
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
     * Extract channel identity from a forwarded message.
     *
     * @return array{chat_id: string, title: string, username: ?string, is_private: bool}
     *
     * @throws RuntimeException when the message isn't from a channel
     */
    public function extract(?Message $message): array
    {
        $chat = $this->channelFromMessage($message);

        if (! $chat || ! $chat->isChannel()) {
            throw new RuntimeException('پیام باید از یک کانال فوروارد شود.');
        }

        return [
            'chat_id' => (string) $chat->id,
            'title' => $chat->title ?? 'کانال',
            'username' => $chat->username,
            'is_private' => $chat->username === null,
        ];
    }

    /**
     * Persist (or update) a required channel with an admin-supplied invite link.
     * Falls back to the public username link when no link is given.
     *
     * @param  array{chat_id: string, title: string, username: ?string, is_private: bool}  $data
     */
    public function save(array $data, ?string $inviteLink): RequiredChannel
    {
        return RequiredChannel::updateOrCreate(
            ['chat_id' => $data['chat_id']],
            [
                'title' => $data['title'],
                'username' => $data['username'],
                'is_private' => $data['is_private'],
                'invite_link' => $inviteLink
                    ?: ($data['username'] ? 'https://t.me/'.$data['username'] : null),
                'is_active' => true,
            ],
        );
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
