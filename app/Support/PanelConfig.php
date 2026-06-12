<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Resolves the web-panel path and on/off state from DB settings (bot-editable),
 * falling back to env-backed config. Reads are guarded so they never break early
 * artisan boots (e.g. before the settings table exists during a fresh migrate).
 */
class PanelConfig
{
    /** URL path segment for the Filament panel, e.g. "admin" or "secret-x9". */
    public static function path(): string
    {
        $default = trim((string) config('v2raybot.panel.path', 'admin'), '/') ?: 'admin';

        try {
            $path = trim(Setting::string(SettingKey::ADMIN_PATH, ''), '/');

            return $path !== '' ? $path : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    /** Whether the web panel is currently reachable. */
    public static function enabled(): bool
    {
        $default = (bool) config('v2raybot.panel.enabled', true);

        try {
            return Setting::bool(SettingKey::WEB_PANEL_ENABLED, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
