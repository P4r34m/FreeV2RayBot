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

    /**
     * Turn a message into HTML for storage, converting every premium (custom)
     * emoji into a <tg-emoji emoji-id="..."> tag at its exact position while
     * leaving the rest of the text verbatim (so admin-typed HTML is preserved).
     * Telegram entity offsets/lengths are UTF-16 code units.
     */
    public static function toHtml(?Message $message): string
    {
        $text = (string) ($message?->text ?? '');

        if ($text === '') {
            return '';
        }

        $emojis = [];
        foreach ($message?->entities ?? [] as $entity) {
            $type = $entity->type instanceof MessageEntityType ? $entity->type->value : $entity->type;
            if ($type === 'custom_emoji' && $entity->custom_emoji_id !== null && ctype_digit((string) $entity->custom_emoji_id)) {
                $emojis[] = $entity;
            }
        }

        if ($emojis === []) {
            return $text; // nothing premium → store the text exactly as typed
        }

        usort($emojis, fn ($a, $b) => $a->offset <=> $b->offset);

        $u16 = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
        $out = '';
        $cursor = 0; // position in UTF-16 code units

        foreach ($emojis as $e) {
            if ($e->offset < $cursor) {
                continue; // overlapping/duplicate entity — skip defensively
            }

            $before = substr($u16, $cursor * 2, ($e->offset - $cursor) * 2);
            $fallback = substr($u16, $e->offset * 2, $e->length * 2);

            $out .= mb_convert_encoding($before, 'UTF-8', 'UTF-16BE')
                .'<tg-emoji emoji-id="'.$e->custom_emoji_id.'">'
                .mb_convert_encoding($fallback, 'UTF-8', 'UTF-16BE')
                .'</tg-emoji>';

            $cursor = $e->offset + $e->length;
        }

        $out .= mb_convert_encoding(substr($u16, $cursor * 2), 'UTF-8', 'UTF-16BE');

        return $out;
    }
}
