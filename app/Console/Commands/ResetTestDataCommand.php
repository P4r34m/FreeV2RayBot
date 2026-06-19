<?php

namespace App\Console\Commands;

use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use App\Models\Referral;
use App\Models\ReferralRewardGrant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipe TEST data — bot users, issued configs, referrals and reward grants — while
 * KEEPING panels, plans, coin packages, referral rules, content and all settings.
 * Resets each panel's active_config_count.
 *
 * Note: clients already created on the remote panels are NOT removed by this.
 */
class ResetTestDataCommand extends Command
{
    protected $signature = 'bot:reset-data {--force : Skip the confirmation prompt}';

    protected $description = 'Delete test data (users/configs/referrals); keep panels, plans, packages, content and settings';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This permanently deletes ALL bot users, configs and referrals. Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            ReferralRewardGrant::query()->delete();
            Referral::query()->delete();
            Config::query()->delete();
            BotUser::query()->update(['referred_by' => null]); // break the self-FK first
            BotUser::query()->delete();
            Panel::query()->update(['active_config_count' => 0]);
        });

        $this->info('✅ Test data cleared. Panels, plans, packages, rules, content and settings kept.');

        return self::SUCCESS;
    }
}
