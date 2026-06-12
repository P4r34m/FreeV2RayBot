<?php

namespace App\Services;

use App\Models\ReportTopic;
use App\Models\Setting;
use App\Support\SettingKey;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Creates one forum topic per report event inside the admin reports group, naming
 * each with the FreeBot brand prefix and storing the returned thread id so reports
 * route to their topic automatically.
 *
 * Requires the bot to be an administrator of the (forum-enabled) group with the
 * can_manage_topics right. Failures are reported per-topic rather than thrown so a
 * partial setup never crashes the webhook.
 */
class ReportTopicProvisioner
{
    /**
     * Ensure a forum topic exists for every report event in the configured group.
     * Existing topics (those that already have a thread id) are left alone unless
     * $recreate is true.
     *
     * @return array{created: int, skipped: int, failed: int, error: ?string}
     */
    public function provision(Nutgram $bot, ?string $groupId = null, bool $recreate = false): array
    {
        $groupId = $groupId ?? Setting::string(SettingKey::REPORTS_GROUP_ID);

        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'error' => null];

        if ($groupId === '') {
            $result['error'] = 'گروه گزارشات تنظیم نشده است.';

            return $result;
        }

        foreach (array_keys(ReportTopic::defaults()) as $event) {
            $topic = ReportTopic::firstOrCreate(
                ['event' => $event],
                ['title' => ReportTopic::brandedName($event)],
            );

            if (! $recreate && $topic->thread_id) {
                $result['skipped']++;

                continue;
            }

            $name = ReportTopic::brandedName($event, $topic->title);

            try {
                $forum = $bot->createForumTopic(chat_id: $groupId, name: $name);
                $threadId = $forum?->message_thread_id;

                if (! $threadId) {
                    $result['failed']++;

                    continue;
                }

                $topic->update([
                    'title' => $name,
                    'thread_id' => $threadId,
                    'is_active' => true,
                ]);
                $result['created']++;
            } catch (Throwable $e) {
                $result['failed']++;
                $result['error'] = $result['error'] ?? $e->getMessage();
            }
        }

        return $result;
    }

    /** Human-readable Persian summary of a provision() result. */
    public function summary(array $result): string
    {
        if ($result['error'] !== null && $result['created'] === 0) {
            return "⚠️ ساخت تاپیک‌ها انجام نشد: <code>".htmlspecialchars((string) $result['error'])."</code>\n".
                'مطمئن شوید ربات در گروه ادمین است، دسترسی «مدیریت تاپیک‌ها» دارد و حالت Topics گروه فعال است.';
        }

        $line = "🧵 تاپیک‌های گزارش — ساخته‌شده: <b>{$result['created']}</b>، رد‌شده: <b>{$result['skipped']}</b>، ناموفق: <b>{$result['failed']}</b>";

        if ($result['failed'] > 0) {
            $line .= "\n⚠️ برخی تاپیک‌ها ساخته نشد؛ دسترسی «مدیریت تاپیک‌ها» ربات را بررسی کنید.";
        }

        return $line;
    }
}
