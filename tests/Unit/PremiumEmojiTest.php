<?php

namespace Tests\Unit;

use App\Support\PremiumEmoji;
use PHPUnit\Framework\TestCase;
use SergiX44\Nutgram\Telegram\Properties\MessageEntityType;
use SergiX44\Nutgram\Telegram\Types\Message\Message;
use SergiX44\Nutgram\Telegram\Types\Message\MessageEntity;

/** PremiumEmoji::extract pulls a custom-emoji id out and returns the cleaned text. */
class PremiumEmojiTest extends TestCase
{
    private function message(string $text, array $entities = []): Message
    {
        $m = new Message();
        $m->text = $text;
        $m->entities = $entities;

        return $m;
    }

    public function test_extracts_leading_premium_emoji_and_strips_it(): void
    {
        // "🔥" is U+1F525 -> a surrogate pair -> 2 UTF-16 code units at offset 0.
        $message = $this->message('🔥 آلمان', [
            MessageEntity::make(MessageEntityType::CUSTOM_EMOJI, 0, 2, custom_emoji_id: '555'),
        ]);

        [$text, $icon] = PremiumEmoji::extract($message);

        $this->assertSame('آلمان', $text);
        $this->assertSame('555', $icon);
    }

    public function test_returns_null_icon_when_no_custom_emoji(): void
    {
        [$text, $icon] = PremiumEmoji::extract($this->message('آلمان'));

        $this->assertSame('آلمان', $text);
        $this->assertNull($icon);
    }

    public function test_ignores_non_custom_emoji_entities(): void
    {
        $message = $this->message('آلمان', [
            MessageEntity::make(MessageEntityType::BOLD, 0, 5),
        ]);

        [$text, $icon] = PremiumEmoji::extract($message);

        $this->assertSame('آلمان', $text);
        $this->assertNull($icon);
    }

    public function test_null_message_yields_empty_text(): void
    {
        [$text, $icon] = PremiumEmoji::extract(null);

        $this->assertSame('', $text);
        $this->assertNull($icon);
    }
}
