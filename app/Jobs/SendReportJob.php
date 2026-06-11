<?php

namespace App\Jobs;

use App\Models\ReportTopic;
use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Posts a single report into the admin reports group, in the forum topic mapped
 * to the event (or the General topic when none is configured).
 */
class SendReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $event, public string $text) {}

    public function handle(Nutgram $bot): void
    {
        $groupId = Setting::string(SettingKey::REPORTS_GROUP_ID);
        if ($groupId === '') {
            return;
        }

        $threadId = ReportTopic::where('event', $this->event)
            ->where('is_active', true)
            ->value('thread_id');

        try {
            $bot->sendMessage(
                text: $this->text,
                chat_id: $groupId,
                message_thread_id: $threadId ?: null,
                parse_mode: 'HTML',
                disable_web_page_preview: true,
            );
        } catch (Throwable) {
            // Group not configured correctly / bot removed — don't crash the queue.
        }
    }
}
