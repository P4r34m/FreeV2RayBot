<?php

namespace App\Telegram\Conversations;

use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\BotUser;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Two-step admin flow: ask for the broadcast content, capture text/media, then
 * queue delivery to all non-blocked users.
 */
class BroadcastConversation extends Conversation
{
    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "📢 پیام همگانی را ارسال کنید (متن، عکس، ویدیو یا فایل با کپشن).\nبرای لغو، /cancel را بفرستید."
        );

        $this->next('capture');
    }

    public function capture(Nutgram $bot): void
    {
        $message = $bot->message();

        if (($message?->text ?? '') === '/cancel') {
            $bot->sendMessage('ارسال پیام همگانی لغو شد.');
            $this->end();

            return;
        }

        [$mediaType, $fileId] = $this->extractMedia($message);
        $text = $message?->text ?? $message?->caption;

        if (! $text && ! $fileId) {
            $bot->sendMessage('پیام نامعتبر بود. دوباره ارسال کنید یا /cancel را بزنید.');
            $this->next('capture');

            return;
        }

        $broadcast = Broadcast::create([
            'message' => $text,
            'media_type' => $mediaType,
            'media_file_id' => $fileId,
            'status' => 'pending',
            'total' => BotUser::where('is_blocked', false)->count(),
        ]);

        SendBroadcastJob::dispatch($broadcast->id);

        $bot->sendMessage("✅ پیام در صف ارسال قرار گرفت.\nتعداد گیرندگان: {$broadcast->total}");
        $this->end();
    }

    /** @return array{0: ?string, 1: ?string} [mediaType, fileId] */
    protected function extractMedia($message): array
    {
        if ($message?->photo) {
            $last = end($message->photo);

            return ['photo', $last->file_id ?? null];
        }
        if ($message?->video) {
            return ['video', $message->video->file_id ?? null];
        }
        if ($message?->document) {
            return ['document', $message->document->file_id ?? null];
        }

        return [null, null];
    }
}
