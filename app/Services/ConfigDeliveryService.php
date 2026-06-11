<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Decides how a config is delivered to the user: a single subscription link, or
 * the individual protocol links (fetched/decoded from the subscription URL).
 */
class ConfigDeliveryService
{
    public const MODE_SUB = 'sub';

    public const MODE_CONFIGS = 'configs';

    public function mode(): string
    {
        return Setting::string(SettingKey::DELIVERY_MODE, self::MODE_SUB) === self::MODE_CONFIGS
            ? self::MODE_CONFIGS
            : self::MODE_SUB;
    }

    /**
     * Individual protocol links for a config. Prefers links the driver already
     * returned, otherwise fetches and decodes the subscription content.
     *
     * @return list<string>
     */
    public function fetchLinks(Config $config): array
    {
        if (! empty($config->config_links)) {
            return array_values($config->config_links);
        }

        $url = $config->subscription_url;
        if (! $url) {
            return [];
        }

        try {
            $response = Http::withoutVerifying()->timeout(15)
                ->withUserAgent('v2rayNG/1.8')
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            return $this->parse($response->body());
        } catch (Throwable) {
            return [];
        }
    }

    /** Subscription bodies are usually base64 of newline-separated links. */
    protected function parse(string $body): array
    {
        $body = trim($body);

        $decoded = base64_decode($body, true);
        if ($decoded !== false && str_contains($decoded, '://')) {
            $body = $decoded;
        }

        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            fn (string $line) => (bool) preg_match('#^(vless|vmess|trojan|ss|hysteria2?|tuic)://#i', $line),
        ));
    }
}
