<?php

namespace App\Telegram;

use App\Models\BotUser;
use App\Models\Config;
use App\Support\Bytes;

/**
 * Builds the HTML message bodies the bot sends (config delivery, account
 * status, referral panel) from the DB-driven Content store templates.
 */
class Presenter
{
    /** Delivery message for a freshly issued/renewed config. */
    public static function configCaption(Config $config): string
    {
        $url = htmlspecialchars((string) $config->subscription_url, ENT_QUOTES);
        $limit = $config->data_limit_bytes > 0 ? Bytes::human($config->data_limit_bytes) : 'نامحدود ♾';

        return Content::text('config.caption', [
            'limit' => $limit,
            'expiry' => $config->expiryHuman(),
            'url' => $url,
        ]);
    }

    /** Account/status summary for a single config. */
    public static function accountStatus(?Config $config): string
    {
        if (! $config) {
            return Content::text('account.no_config');
        }

        $limit = $config->data_limit_bytes > 0 ? Bytes::human($config->data_limit_bytes) : 'نامحدود ♾';
        $remaining = $config->data_limit_bytes > 0 ? Bytes::human($config->remainingBytes()) : 'نامحدود ♾';
        $url = htmlspecialchars((string) $config->subscription_url, ENT_QUOTES);

        return Content::text('account.status', [
            'status' => $config->status->label(),
            'used' => $config->usedHuman(), // "۰" when nothing used (not "unlimited")
            'limit' => $limit,
            'remaining' => $remaining,
            'expiry' => $config->expiryHuman(),
            'url' => $url,
        ]);
    }

    /** The referral panel body. */
    public static function referral(BotUser $user, string $link, string $rulesText): string
    {
        $bonusTraffic = $user->bonus_traffic_bytes > 0 ? Bytes::human($user->bonus_traffic_bytes) : '۰';
        $bonusDays = $user->bonus_days;

        return Content::text('referral.body', [
            'link' => htmlspecialchars($link, ENT_QUOTES),
            'count' => $user->referral_count,
            'bonus_traffic' => $bonusTraffic,
            'bonus_days' => $bonusDays,
            'rules' => $rulesText,
        ]);
    }
}
