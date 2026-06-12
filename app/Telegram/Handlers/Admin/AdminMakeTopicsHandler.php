<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Services\ReportTopicProvisioner;
use App\Support\SettingKey;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/**
 * (Re)create the FreeBot-branded report forum topics in the configured group
 * (callback: admin:maketopics). Only fills in topics that have no thread id yet,
 * so it is safe to press repeatedly.
 */
class AdminMakeTopicsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $groupId = Setting::string(SettingKey::REPORTS_GROUP_ID);

        if ($groupId === '') {
            Reply::screen(
                $bot,
                '⚠️ ابتدا «گروه گزارشات» را تنظیم کنید، سپس تاپیک‌ها ساخته می‌شوند.',
                Keyboards::single('common.back', 'admin:settings'),
            );

            return;
        }

        $provisioner = app(ReportTopicProvisioner::class);
        $result = $provisioner->provision($bot, $groupId);

        Reply::screen(
            $bot,
            $provisioner->summary($result),
            Keyboards::single('common.back', 'admin:settings'),
        );
    }
}
