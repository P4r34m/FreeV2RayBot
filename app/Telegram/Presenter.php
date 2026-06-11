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
        $expiry = $config->expires_at
            ? self::jalaliFriendly($config->expires_at->timestamp).' ('.$config->expires_at->diffForHumans().')'
            : 'نامحدود ♾';

        return Content::text('config.caption', [
            'limit' => $limit,
            'expiry' => $expiry,
            'url' => $url,
        ]);
    }

    /** Account/status summary for the user's active config. */
    public static function accountStatus(?Config $config): string
    {
        if (! $config) {
            return Content::text('account.no_config');
        }

        $used = Bytes::human($config->used_bytes);
        $limit = $config->data_limit_bytes > 0 ? Bytes::human($config->data_limit_bytes) : 'نامحدود ♾';
        $remaining = $config->data_limit_bytes > 0 ? Bytes::human($config->remainingBytes()) : 'نامحدود ♾';
        $expiry = $config->expires_at
            ? $config->expires_at->diffForHumans()
            : 'نامحدود ♾';
        $url = htmlspecialchars((string) $config->subscription_url, ENT_QUOTES);

        return Content::text('account.status', [
            'status' => $config->status->label(),
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'expiry' => $expiry,
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

    /** Lightweight Gregorian date string (Jalali conversion left to display libs). */
    protected static function jalaliFriendly(int $timestamp): string
    {
        return date('Y-m-d', $timestamp);
    }
}
