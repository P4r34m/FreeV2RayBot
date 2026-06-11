<?php

namespace App\Services;

use App\Jobs\SendReportJob;
use App\Models\Setting;
use App\Support\SettingKey;

/**
 * Sends operational reports into the admin forum group, routed to a per-event
 * topic. Dispatched async so it never slows the webhook.
 */
class ReportService
{
    public const NEW_USER = 'new_user';

    public const NEW_CONFIG = 'new_config';

    public const RENEW = 'renew';

    public const REFERRAL = 'referral';

    public const CHANNEL_JOIN = 'channel_join';

    public const BLOCKED = 'blocked';

    public const ERROR = 'error';

    public function enabled(): bool
    {
        return Setting::bool(SettingKey::REPORTS_ENABLED)
            && Setting::string(SettingKey::REPORTS_GROUP_ID) !== '';
    }

    public function send(string $event, string $text): void
    {
        if (! $this->enabled()) {
            return;
        }

        SendReportJob::dispatch($event, $text);
    }
}
