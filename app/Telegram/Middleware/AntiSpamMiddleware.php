<?php

namespace App\Telegram\Middleware;

use App\Models\BotUser;
use App\Models\Setting;
use App\Services\ReportService;
use App\Support\SettingKey;
use App\Telegram\Content;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;

/**
 * Flood control: enforces any active temporary block, then rate-limits actions
 * per user in a fixed window. Exceeding the limit applies a temporary block and
 * reports it. Admins are exempt.
 */
class AntiSpamMiddleware
{
    /** User-facing times are shown in Tehran local time. */
    private const DISPLAY_TZ = 'Asia/Tehran';

    public function __construct(private readonly ReportService $reports) {}

    /** Format a timestamp in Tehran local time. */
    private function fmt(\Carbon\CarbonInterface $dt): string
    {
        return $dt->copy()->setTimezone(self::DISPLAY_TZ)->format('Y-m-d H:i');
    }

    public function __invoke(Nutgram $bot, $next): void
    {
        $user = $bot->get('botUser');

        // Enforce an existing temporary block.
        if ($user instanceof BotUser && $user->blocked_until && $user->blocked_until->isFuture()) {
            $bot->sendMessage(Content::text('blocked.temporary', [
                'until' => $this->fmt($user->blocked_until),
            ]));

            return;
        }

        $isAdmin = $user instanceof BotUser && $user->is_admin;

        if ($isAdmin || ! Setting::bool(SettingKey::ANTISPAM_ENABLED, true)) {
            $next($bot);

            return;
        }

        $max = max(1, Setting::int(SettingKey::ANTISPAM_MAX_ACTIONS, 20));
        $window = max(1, Setting::int(SettingKey::ANTISPAM_WINDOW_SECONDS, 60));
        $key = 'antispam:'.$bot->userId();

        // Fixed window: keep the original TTL across increments.
        $count = Cache::add($key, 1, $window) ? 1 : (int) Cache::increment($key);

        if ($count > $max) {
            $this->block($bot, $user);

            return;
        }

        $next($bot);
    }

    protected function block(Nutgram $bot, ?BotUser $user): void
    {
        $minutes = max(1, Setting::int(SettingKey::ANTISPAM_BLOCK_MINUTES, 10));
        $until = now()->addMinutes($minutes);

        if ($user instanceof BotUser) {
            $user->update([
                'blocked_until' => $until,
                'spam_strikes' => $user->spam_strikes + 1,
                'block_reason' => 'anti-spam',
            ]);

            $this->reports->send(
                ReportService::BLOCKED,
                "🚫 <b>بلاک موقت (اسپم)</b>\nکاربر: {$user->displayHandle()} (<code>{$user->telegram_id}</code>)\nتا: {$this->fmt($until)}",
            );
        }

        $bot->sendMessage(Content::text('blocked.temporary', ['until' => $this->fmt($until)]));
    }
}
