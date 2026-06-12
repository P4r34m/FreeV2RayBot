<?php

namespace App\Telegram\Conversations;

use App\Models\Plan;
use App\Support\Bytes;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to create a plan: name => data (GB, 0=unlimited) => duration
 * (days, 0=unlimited) => Plan::create. Traffic is stored in bytes.
 */
class AddPlanConversation extends Conversation
{
    public ?string $name = null;

    public ?int $dataLimitBytes = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "📦 نام پلن را وارد کنید.\n\nبرای لغو: /cancel"
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
            $bot->sendMessage('نام نامعتبر است. یک نام معتبر بفرستید یا /cancel.');
            $this->next('captureName');

            return;
        }

        $this->name = $text;

        $bot->sendMessage(
            "💾 حجم پلن را بر حسب گیگابایت وارد کنید (عدد).\n".
            "برای نامحدود عدد <code>0</code> را بفرستید.\n\nبرای لغو: /cancel",
            parse_mode: 'HTML',
        );

        $this->next('captureData');
    }

    public function captureData(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! is_numeric($text) || (float) $text < 0) {
            $bot->sendMessage('حجم نامعتبر است. یک عدد بفرستید (0 = نامحدود) یا /cancel.');
            $this->next('captureData');

            return;
        }

        $this->dataLimitBytes = Bytes::fromGb((float) $text);

        $bot->sendMessage(
            "⏳ مدت پلن را بر حسب روز وارد کنید (عدد صحیح).\n".
            "برای نامحدود عدد <code>0</code> را بفرستید.\n\nبرای لغو: /cancel",
            parse_mode: 'HTML',
        );

        $this->next('captureDuration');
    }

    public function captureDuration(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text)) {
            $bot->sendMessage('مدت نامعتبر است. یک عدد صحیح بفرستید (0 = نامحدود) یا /cancel.');
            $this->next('captureDuration');

            return;
        }

        $plan = Plan::create([
            'name' => $this->name,
            'data_limit_bytes' => $this->dataLimitBytes,
            'duration_days' => (int) $text,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => (int) (Plan::max('sort_order') + 1),
        ]);

        $data = $plan->data_limit_bytes > 0 ? Bytes::human($plan->data_limit_bytes) : 'نامحدود';
        $duration = $plan->duration_days > 0 ? $plan->duration_days.' روز' : 'نامحدود';

        $bot->sendMessage(
            "✅ پلن «{$plan->name}» ساخته شد.\n💾 حجم: {$data}\n⏳ مدت: {$duration}",
        );

        $this->end();
    }
}
