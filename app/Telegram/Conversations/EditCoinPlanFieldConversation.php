<?php

namespace App\Telegram\Conversations;

use App\Models\CoinPlan;
use App\Support\Bytes;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/** Edit a coin package field from the bot: name, data (GB), duration (days), price (coins). */
class EditCoinPlanFieldConversation extends Conversation
{
    private const FIELDS = ['name', 'data', 'duration', 'price'];

    public ?int $planId = null;

    public ?string $field = null;

    public function start(Nutgram $bot): void
    {
        if (! in_array($this->field, self::FIELDS, true) || ! CoinPlan::whereKey($this->planId)->exists()) {
            $bot->sendMessage('مورد نامعتبر است.');
            $this->end();

            return;
        }

        $prompt = match ($this->field) {
            'name' => '✏️ نام جدید بسته را بفرستید.',
            'data' => '📦 حجم را به گیگابایت بفرستید (0 = نامحدود).',
            'duration' => '⏳ مدت را به روز بفرستید (0 = بدون انقضا).',
            'price' => '🪙 قیمت را به سکه بفرستید.',
        };

        $bot->sendMessage($prompt."\n\nبرای لغو: /cancel");
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

        $plan = CoinPlan::find($this->planId);
        if (! $plan) {
            $bot->sendMessage('بسته پیدا نشد.');
            $this->end();

            return;
        }

        if ($this->field === 'name') {
            if ($text === '') {
                $bot->sendMessage('نام خالی است. دوباره بفرستید یا /cancel.');
                $this->next('capture');

                return;
            }
            $plan->name = $text;
        } elseif ($this->field === 'data') {
            if (! is_numeric($text) || (float) $text < 0) {
                $bot->sendMessage('عدد نامعتبر است (گیگابایت). دوباره بفرستید یا /cancel.');
                $this->next('capture');

                return;
            }
            $plan->data_limit_bytes = Bytes::fromGb((float) $text);
        } elseif ($this->field === 'duration') {
            if (! ctype_digit($text)) {
                $bot->sendMessage('عدد روز نامعتبر است. دوباره بفرستید یا /cancel.');
                $this->next('capture');

                return;
            }
            $plan->duration_days = (int) $text;
        } else { // price
            if (! ctype_digit($text) || (int) $text < 1) {
                $bot->sendMessage('قیمت نامعتبر است. یک عدد بزرگ‌تر از صفر بفرستید یا /cancel.');
                $this->next('capture');

                return;
            }
            $plan->coin_price = (int) $text;
        }

        $plan->save();
        $bot->sendMessage('✅ ذخیره شد.');
        $this->end();
    }
}
