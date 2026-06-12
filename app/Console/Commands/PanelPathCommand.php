<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\PanelConfig;
use App\Support\SettingKey;
use Illuminate\Console\Command;

/**
 * Set or show the web panel URL path from the server, e.g.:
 *   docker compose exec web php artisan panel:path secret-x9k2
 * Applies live (the web container reads the path per request).
 */
class PanelPathCommand extends Command
{
    protected $signature = 'panel:path {value? : new path segment; omit to show current}';

    protected $description = 'Set or show the web admin panel path';

    public function handle(): int
    {
        $value = $this->argument('value');

        if ($value === null) {
            $this->info('Current panel path: /'.PanelConfig::path());

            return self::SUCCESS;
        }

        $clean = trim((string) $value, '/');

        if (! preg_match('/^[A-Za-z0-9._-]{2,40}$/', $clean)) {
            $this->error('Invalid path. Use 2–40 chars: letters, digits, dot, dash, underscore.');

            return self::FAILURE;
        }

        Setting::put(SettingKey::ADMIN_PATH, $clean);
        $this->info("Panel path set. Open it at: /{$clean}");

        return self::SUCCESS;
    }
}
