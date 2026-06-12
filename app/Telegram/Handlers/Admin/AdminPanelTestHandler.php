<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Panel;
use App\Panels\Exceptions\PanelException;
use App\Panels\PanelManager;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/** Run a live connection test against a panel (callback: admin:panels:test:{id}). */
class AdminPanelTestHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $panel = Panel::find((int) $id);

        if ($panel === null) {
            Reply::toast($bot, '❌ پنل یافت نشد', alert: true);
            (new AdminPanelsHandler)($bot);

            return;
        }

        try {
            $ok = app(PanelManager::class)->driver($panel)->testConnection();

            $panel->update([
                'health_status' => $ok ? 'ok' : 'failed',
                'health_message' => $ok ? null : 'تست اتصال ناموفق بود',
                'last_health_check_at' => now(),
            ]);

            $ok
                ? Reply::toast($bot, '✅ اتصال موفق')
                : Reply::toast($bot, '❌ خطا: تست اتصال ناموفق بود', alert: true);
        } catch (PanelException|Throwable $e) {
            $panel->update([
                'health_status' => 'failed',
                'health_message' => mb_substr($e->getMessage(), 0, 250),
                'last_health_check_at' => now(),
            ]);

            Reply::toast($bot, '❌ خطا: '.$e->getMessage(), alert: true);
        }

        AdminPanelViewHandler::render($bot, $panel->refresh());
    }
}
