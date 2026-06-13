<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\BotText;
use App\Telegram\ContentDefaults;
use App\Telegram\Conversations\EditTextConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Browse and edit user-facing texts as glass buttons — category → text → edit —
 * so the admin never has to type/search a key (callback: admin:content:edittext).
 */
class AdminEditTextHandler
{
    /** Friendly labels for the key prefixes (category before the first dot). */
    private const CATEGORIES = [
        'general' => '🌐 عمومی',
        'config' => '🎁 کانفیگ',
        'account' => '📊 حساب',
        'profile' => '👤 پروفایل',
        'referral' => '👥 رفرال',
        'coin' => '🪙 سکه و فروشگاه',
        'tutorials' => '📚 آموزش‌ها',
        'channel' => '📡 کانال',
        'blocked' => '⛔️ بلاک',
        'antispam' => '🛡 ضداسپم',
        'bot' => '🤖 ربات',
        'common' => '🔧 دکمه‌های عمومی',
    ];

    /** Level 1: list the text categories present. */
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $cats = [];
        foreach (array_keys(ContentDefaults::texts()) as $key) {
            $cats[self::categoryOf($key)] = true;
        }

        $kb = InlineKeyboardMarkup::make();
        foreach (array_keys($cats) as $cat) {
            $kb->addRow(Btn::make(self::CATEGORIES[$cat] ?? $cat, callback_data: 'admin:content:txtcat:'.$cat));
        }
        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:content'));

        Reply::screen($bot, "✏️ <b>ویرایش متن‌ها</b>\nیک دسته را انتخاب کنید:", $kb);
    }

    /** Level 2: list the texts in a category with a content preview. */
    public static function category(Nutgram $bot, string $cat): void
    {
        Reply::toast($bot);

        $kb = InlineKeyboardMarkup::make();
        foreach (array_keys(ContentDefaults::texts()) as $key) {
            if (self::categoryOf($key) !== $cat) {
                continue;
            }
            $kb->addRow(Btn::make(self::preview($key), callback_data: 'admin:content:edittext:'.$key));
        }
        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:content:edittext'));

        Reply::screen($bot, '✏️ یک متن را برای ویرایش انتخاب کنید:', $kb);
    }

    /** Level 3: edit a specific text (callback: admin:content:edittext:{key}). */
    public static function editKey(Nutgram $bot, string $key): void
    {
        Reply::toast($bot);

        if (! array_key_exists($key, ContentDefaults::texts())) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        /** @var EditTextConversation $conv */
        $conv = $bot->getContainer()->get(EditTextConversation::class);
        $conv->key = $key;
        $conv($bot);
    }

    private static function categoryOf(string $key): string
    {
        return str_contains($key, '.') ? strstr($key, '.', true) : 'general';
    }

    /** A short, plain-text preview of the current value for a button label. */
    private static function preview(string $key): string
    {
        $content = BotText::where('key', $key)->value('content') ?? ContentDefaults::texts()[$key] ?? $key;
        $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($content)));

        if ($plain === '') {
            return $key;
        }

        return mb_strlen($plain) > 32 ? mb_substr($plain, 0, 32).'…' : $plain;
    }
}
