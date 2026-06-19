<?php

namespace App\Support;

use SergiX44\Nutgram\Telegram\Properties\MessageEntityType;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

/** Helpers for working with premium (custom) emoji in admin-typed messages. */
class PremiumEmoji
{
    /**
     * Pull the first premium (custom) emoji id out of a message and return
     * [textWithoutThatEmoji, customEmojiId|null]. Telegram entity offsets/lengths
     * are counted in UTF-16 code units, so we splice in UTF-16BE.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function extract(?Message $message): array
    {
        $text = trim((string) ($message?->text ?? ''));

        foreach ($message?->entities ?? [] as $entity) {
            $type = $entity->type instanceof MessageEntityType ? $entity->type->value : $entity->type;

            if ($type === 'custom_emoji' && $entity->custom_emoji_id) {
                $u16 = mb_convert_encoding((string) $message->text, 'UTF-16BE', 'UTF-8');
                $stripped = substr($u16, 0, $entity->offset * 2)
                    .substr($u16, ($entity->offset + $entity->length) * 2);

                return [trim((string) mb_convert_encoding($stripped, 'UTF-8', 'UTF-16BE')), $entity->custom_emoji_id];
            }
        }

        return [$text, null];
    }
}
