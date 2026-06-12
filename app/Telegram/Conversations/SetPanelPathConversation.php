<?php

namespace App\Telegram\Conversations;

use App\Models\Setting;
use App\Support\PanelConfig;
use App\Support\SettingKey;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to change the web panel URL path (security via a secret path).
 * Applies live — the web container reads the path per request.
 */
class SetPanelPathConversation extends Conversation
{
    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "🔐 مسیر فعلی پنل وب: <code>/".PanelConfig::path()."</code>\n\n".
            "مسیر جدید را بفرستید (۲ تا ۴۰ کاراکتر: حروف، اعداد، خط‌تیره). مثال: <code>secret-x9k2</code>\n\n".
            'برای لغو: /cancel',
            parse_mode: 'HTML',
        );

        $this->next('capture');
    }

    public function capture(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $clean = trim($text, '/');

        if (! preg_match('/^[A-Za-z0-9._-]{2,40}$/', $clean)) {
            $bot->sendMessage('مسیر نامعتبر است. فقط ۲ تا ۴۰ کاراکتر حروف/عدد/خط‌تیره. دوباره بفرستید یا /cancel.');
            $this->next('capture');

            return;
        }

        Setting::put(SettingKey::ADMIN_PATH, $clean);
        $url = rtrim((string) config('app.url'), '/').'/'.$clean;

        $bot->sendMessage(
            "✅ مسیر پنل وب تغییر کرد.\n🔗 آدرس جدید: <code>{$url}</code>\n\n⚠️ مسیر قبلی دیگر کار نمی‌کند.",
            parse_mode: 'HTML',
        );

        $this->end();
    }
}
