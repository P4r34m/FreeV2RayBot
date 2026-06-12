<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\PanelConfig;
use App\Support\SettingKey;
use Illuminate\Console\Command;

/**
 * Turn the web admin panel on/off from the server, e.g.:
 *   docker compose exec web php artisan panel:web off
 * Same switch the bot toggles, so server and bot stay in sync.
 */
class WebPanelCommand extends Command
{
    protected $signature = 'panel:web {action : on | off | status}';

    protected $description = 'Enable or disable the web admin panel';

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));

        match ($action) {
            'on', 'enable' => $this->set(true),
            'off', 'disable' => $this->set(false),
            'status' => null,
            default => $this->error("Unknown action [{$action}]. Use on | off | status."),
        };

        if (! in_array($action, ['on', 'enable', 'off', 'disable', 'status'], true)) {
            return self::FAILURE;
        }

        $this->info('Web panel is now: '.(PanelConfig::enabled() ? 'ON' : 'OFF')
            .' at /'.PanelConfig::path());

        return self::SUCCESS;
    }

    private function set(bool $enabled): void
    {
        Setting::put(SettingKey::WEB_PANEL_ENABLED, $enabled);
    }
}
