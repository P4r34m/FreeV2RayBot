<?php

namespace App\Telegram;

use App\Models\BotButton;
use App\Models\BotText;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;

/**
 * Resolves user-facing texts and button labels from the admin-editable
 * bot_texts / bot_buttons tables (cached), falling back to ContentDefaults.
 * Supports {placeholder} substitution and premium-emoji button icons.
 */
class Content
{
    /** Resolve a text by key with {placeholder} replacements. */
    public static function text(string $key, array $replace = []): string
    {
        $content = static::texts()[$key] ?? ContentDefaults::texts()[$key] ?? $key;

        foreach ($replace as $name => $value) {
            $content = str_replace('{'.$name.'}', (string) $value, $content);
        }

        return $content;
    }

    public static function buttonLabel(string $key): string
    {
        return static::buttons()[$key]['label'] ?? ContentDefaults::buttons()[$key] ?? $key;
    }

    public static function iconEmojiId(string $key): ?string
    {
        return static::buttons()[$key]['icon'] ?? null;
    }

    /** Button color/style for a key: primary|success|danger, or null (default). */
    public static function buttonStyle(string $key): ?string
    {
        return static::buttons()[$key]['style'] ?? null;
    }

    /** Build an inline button from a content key (+ optional premium-emoji icon & color). */
    public static function button(string $key, ?string $callbackData = null, ?string $url = null): Btn
    {
        return Btn::make(
            text: static::buttonLabel($key),
            url: $url,
            callback_data: $callbackData,
            icon_custom_emoji_id: static::iconEmojiId($key),
            style: static::buttonStyle($key),
        );
    }

    /** @return array<string, string> */
    protected static function texts(): array
    {
        return Cache::remember('bot_texts.all', 3600, fn () => BotText::pluck('content', 'key')->all());
    }

    /** @return array<string, array{label: string, icon: ?string, style: ?string}> */
    protected static function buttons(): array
    {
        return Cache::remember('bot_buttons.all', 3600, function () {
            return BotButton::all()->keyBy('key')->map(fn (BotButton $b) => [
                'label' => $b->label,
                'icon' => $b->icon_custom_emoji_id,
                'style' => $b->style,
            ])->all();
        });
    }
}
