<?php

namespace App\Telegram\Conversations;

use App\Models\Tutorial;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to add a tutorial: title => category (or /skip) => content (HTML).
 */
class AddTutorialConversation extends Conversation
{
    public ?string $title = null;

    public ?string $category = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "📚 عنوان آموزش را وارد کنید.\n\nبرای لغو: /cancel"
        );

        $this->next('captureTitle');
    }

    public function captureTitle(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($text === '') {
            $bot->sendMessage('عنوان نمی‌تواند خالی باشد. دوباره وارد کنید یا /cancel.');
            $this->next('captureTitle');

            return;
        }

        $this->title = $text;

        $bot->sendMessage(
            "🏷 دسته‌بندی آموزش را وارد کنید، یا /skip را بزنید تا بدون دسته‌بندی ثبت شود.\n\nبرای لغو: /cancel"
        );

        $this->next('captureCategory');
    }

    public function captureCategory(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $this->category = $text === '/skip' ? null : ($text === '' ? null : $text);

        $bot->sendMessage(
            "📝 متن آموزش را ارسال کنید (می‌توانید از تگ‌های HTML مثل <b>, <code> استفاده کنید).\n\nبرای لغو: /cancel",
            parse_mode: 'HTML',
        );

        $this->next('captureContent');
    }

    public function captureContent(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($text === '') {
            $bot->sendMessage('متن آموزش نمی‌تواند خالی باشد. دوباره ارسال کنید یا /cancel.');
            $this->next('captureContent');

            return;
        }

        $tutorial = Tutorial::create([
            'title' => $this->title,
            'category' => $this->category,
            'content' => $text,
            'is_active' => true,
            'sort_order' => (int) Tutorial::max('sort_order') + 1,
        ]);

        $bot->sendMessage("✅ آموزش «{$tutorial->title}» اضافه شد.");

        $this->end();
    }
}
