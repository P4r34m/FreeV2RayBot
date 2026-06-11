<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BotUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Delivers a broadcast to every non-blocked user, throttled to stay under
 * Telegram's bulk limits, updating progress counters on the Broadcast row.
 */
class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $broadcastId) {}

    public function handle(Nutgram $bot): void
    {
        $broadcast = Broadcast::find($this->broadcastId);
        if (! $broadcast || $broadcast->status === 'done') {
            return;
        }

        $broadcast->update(['status' => 'running', 'started_at' => now()]);
        $sent = 0;
        $failed = 0;

        BotUser::where('is_blocked', false)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($bot, $broadcast, &$sent, &$failed) {
                foreach ($users as $user) {
                    try {
                        $this->sendOne($bot, $broadcast, (int) $user->telegram_id);
                        $sent++;
                    } catch (Throwable) {
                        $failed++;
                    }

                    usleep(40000); // ~25 messages/sec
                }

                $broadcast->update(['sent' => $sent, 'failed' => $failed]);
            });

        $broadcast->update([
            'status' => 'done',
            'finished_at' => now(),
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }

    protected function sendOne(Nutgram $bot, Broadcast $broadcast, int $chatId): void
    {
        $caption = $broadcast->message;

        match ($broadcast->media_type) {
            'photo' => $bot->sendPhoto(photo: $broadcast->media_file_id, chat_id: $chatId, caption: $caption, parse_mode: 'HTML'),
            'video' => $bot->sendVideo(video: $broadcast->media_file_id, chat_id: $chatId, caption: $caption, parse_mode: 'HTML'),
            'document' => $bot->sendDocument(document: $broadcast->media_file_id, chat_id: $chatId, caption: $caption, parse_mode: 'HTML'),
            default => $bot->sendMessage(text: (string) $caption, chat_id: $chatId, parse_mode: 'HTML'),
        };
    }
}
