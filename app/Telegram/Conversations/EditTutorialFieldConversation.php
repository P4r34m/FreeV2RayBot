<?php

namespace App\Telegram\Conversations;

use App\Models\Tutorial;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/** Edit a tutorial field from the bot: title, category, or content. */
class EditTutorialFieldConversation extends Conversation
{
    private const FIELDS = ['title', 'category', 'content'];

    public ?int $tutorialId = null;

    public ?string $field = null;

    public function start(Nutgram $bot): void
    {
        if (! in_array($this->field, self::FIELDS, true) || ! Tutorial::whereKey($this->tutorialId)->exists()) {
            $bot->sendMessage('مورد نامعتبر است.');
            $this->end();

            return;
        }

        $prompt = match ($this->field) {
            'title' => '✏️ عنوان جدید آموزش را بفرستید.',
            'category' => '🏷 دسته‌بندی جدید را بفرستید (برای خالی‌کردن: /clear).',
            'content' => '📝 متن جدید آموزش را بفرستید (HTML مجاز است).',
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

        $tutorial = Tutorial::find($this->tutorialId);
        if (! $tutorial) {
            $bot->sendMessage('آموزش پیدا نشد.');
            $this->end();

            return;
        }

        if ($this->field === 'category' && $text === '/clear') {
            $tutorial->category = null;
        } elseif ($text === '') {
            $bot->sendMessage('مقدار خالی است. دوباره بفرستید یا /cancel.');
            $this->next('capture');

            return;
        } else {
            $tutorial->{$this->field} = $text;
        }

        $tutorial->save();
        $bot->sendMessage('✅ ذخیره شد.');
        $this->end();
    }
}
