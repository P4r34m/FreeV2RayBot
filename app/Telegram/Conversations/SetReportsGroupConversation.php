<?php

namespace App\Telegram\Conversations;

use App\Models\Setting;
use App\Support\SettingKey;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to set the reports group: forward any message FROM the group (the
 * bot reads its chat id) or paste the numeric group id directly.
 */
class SetReportsGroupConversation extends Conversation
{
    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "📨 یک پیام را از داخل «گروه گزارشات» به اینجا فوروارد کنید، یا آیدی عددی گروه را ارسال کنید.\n".
            "⚠️ ربات باید در آن گروه عضو/ادمین باشد و گروه باید تاپیک‌بندی (Forum) باشد.\n".
            'برای لغو: /cancel'
        );

        $this->next('capture');
    }

    public function capture(Nutgram $bot): void
    {
        $message = $bot->message();
        $text = trim($message?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        // Forwarded from a group/supergroup, or a typed numeric id.
        $groupId = $message?->forward_origin?->chat?->id
            ?? $message?->forward_from_chat?->id
            ?? (preg_match('/^-?\d+$/', $text) ? (int) $text : null);

        if (! $groupId) {
            $bot->sendMessage('پیدا نشد. یک پیام از گروه فوروارد کنید یا آیدی عددی بفرستید یا /cancel.');
            $this->next('capture');

            return;
        }

        Setting::put(SettingKey::REPORTS_GROUP_ID, (string) $groupId);
        Setting::put(SettingKey::REPORTS_ENABLED, true);

        $bot->sendMessage("✅ گروه گزارشات تنظیم شد: <code>{$groupId}</code>\nگزارش‌دهی فعال شد.", parse_mode: 'HTML');

        // Auto-create the FreeBot-branded forum topics and wire up their thread ids.
        $provisioner = app(\App\Services\ReportTopicProvisioner::class);
        $result = $provisioner->provision($bot, (string) $groupId);
        $bot->sendMessage($provisioner->summary($result), parse_mode: 'HTML');

        $this->end();
    }
}
