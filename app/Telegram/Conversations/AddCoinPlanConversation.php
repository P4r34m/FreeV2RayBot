<?php

namespace App\Telegram\Conversations;

use App\Models\CoinPlan;
use App\Support\Bytes;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to add a coin package: name → volume (GB) → duration (days) →
 * price (coins) → create.
 */
class AddCoinPlanConversation extends Conversation
{
    public ?string $name = null;

    public ?int $bytes = null;

    public ?int $days = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "🛒 <b>افزودن بسته سکه‌ای</b>\n\nنام بسته را ارسال کنید.\nبرای لغو: /cancel",
            parse_mode: 'HTML',
        );

        $this->next('captureName');
    }

    public function captureName(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($text === '') {
            $bot->sendMessage('نام نامعتبر است. دوباره ارسال کنید یا /cancel.');
            $this->next('captureName');

            return;
        }

        $this->name = $text;
        $bot->sendMessage("📦 حجم را به <b>گیگابایت</b> ارسال کنید (0 = نامحدود).\nبرای لغو: /cancel", parse_mode: 'HTML');
        $this->next('captureVolume');
    }

    public function captureVolume(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! is_numeric($text) || (float) $text < 0) {
            $bot->sendMessage('عدد نامعتبر است. حجم به گیگابایت (0 یا بیشتر) ارسال کنید یا /cancel.');
            $this->next('captureVolume');

            return;
        }

        $this->bytes = Bytes::fromGb((float) $text);
        $bot->sendMessage("⏳ مدت را به <b>روز</b> ارسال کنید (0 = بدون انقضا).\nبرای لغو: /cancel", parse_mode: 'HTML');
        $this->next('captureDays');
    }

    public function captureDays(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text)) {
            $bot->sendMessage('عدد نامعتبر است. تعداد روز (0 یا بیشتر) ارسال کنید یا /cancel.');
            $this->next('captureDays');

            return;
        }

        $this->days = (int) $text;
        $bot->sendMessage("🪙 قیمت بسته را به <b>سکه</b> ارسال کنید.\nبرای لغو: /cancel", parse_mode: 'HTML');
        $this->next('capturePrice');
    }

    public function capturePrice(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text) || (int) $text < 1) {
            $bot->sendMessage('قیمت نامعتبر است. یک عدد بزرگ‌تر از صفر ارسال کنید یا /cancel.');
            $this->next('capturePrice');

            return;
        }

        $plan = CoinPlan::create([
            'name' => $this->name,
            'data_limit_bytes' => (int) $this->bytes,
            'duration_days' => (int) $this->days,
            'coin_price' => (int) $text,
            'is_active' => true,
        ]);

        $bot->sendMessage("✅ بسته اضافه شد:\n<b>{$plan->name}</b> — {$plan->label()}", parse_mode: 'HTML');
        $this->end();
    }
}
