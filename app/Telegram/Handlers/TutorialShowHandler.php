<?php

namespace App\Telegram\Handlers;

use App\Models\Tutorial;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Show a single tutorial's content (callback: tutorial:show:{id}). */
class TutorialShowHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $tutorial = Tutorial::find((int) $id);

        if (! $tutorial || ! $tutorial->is_active) {
            Reply::toast($bot, Content::text('tutorials.unavailable'), alert: true);

            return;
        }

        Reply::toast($bot);

        $body = '<b>'.htmlspecialchars($tutorial->title, ENT_QUOTES)."</b>\n\n".$tutorial->content;
        $back = Keyboards::single('tutorials.back', Keyboards::CB_TUTORIALS);

        if ($tutorial->media_file_id) {
            $this->sendMedia($bot, $tutorial, $body, $back);

            return;
        }

        Reply::screen($bot, $body, $back);
    }

    protected function sendMedia(Nutgram $bot, Tutorial $tutorial, string $body, $back): void
    {
        $caption = mb_substr($body, 0, 1024);
        $chatId = (int) $bot->chatId();

        match ($tutorial->media_type) {
            'video' => $bot->sendVideo(video: $tutorial->media_file_id, chat_id: $chatId, caption: $caption, parse_mode: 'HTML', reply_markup: $back),
            'document' => $bot->sendDocument(document: $tutorial->media_file_id, chat_id: $chatId, caption: $caption, parse_mode: 'HTML', reply_markup: $back),
            default => $bot->sendPhoto(photo: $tutorial->media_file_id, chat_id: $chatId, caption: $caption, parse_mode: 'HTML', reply_markup: $back),
        };
    }
}
