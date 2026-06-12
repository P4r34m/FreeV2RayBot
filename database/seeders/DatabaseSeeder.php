<?php

namespace Database\Seeders;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use App\Models\Plan;
use App\Models\ReferralRule;
use App\Models\Setting;
use App\Models\User;
use App\Support\Bytes;
use App\Support\SettingKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedSettings();
        $this->seedDefaultPlan();
        $this->seedSampleReferralRule();
        $this->seedContent();
        $this->seedReportTopics();
    }

    /** Seed editable texts + buttons from the defaults registry (idempotent). */
    protected function seedContent(): void
    {
        foreach (\App\Telegram\ContentDefaults::texts() as $key => $content) {
            \App\Models\BotText::firstOrCreate(['key' => $key], ['content' => $content]);
        }

        foreach (\App\Telegram\ContentDefaults::buttons() as $key => $label) {
            \App\Models\BotButton::firstOrCreate(['key' => $key], ['label' => $label]);
        }
    }

    /**
     * Default report event => topic rows. Titles carry the FreeBot brand prefix;
     * thread ids are filled in when the bot auto-creates the forum topics (see
     * App\Services\ReportTopicProvisioner) after the reports group is configured.
     */
    protected function seedReportTopics(): void
    {
        foreach (array_keys(\App\Models\ReportTopic::defaults()) as $event) {
            \App\Models\ReportTopic::firstOrCreate(
                ['event' => $event],
                ['title' => \App\Models\ReportTopic::brandedName($event)],
            );
        }
    }

    /** First web/Filament admin from env (idempotent). */
    protected function seedAdmin(): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            ],
        );
    }

    protected function seedSettings(): void
    {
        foreach (SettingKey::defaults() as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }
    }

    /** A free default plan from config so issuance works out of the box. */
    protected function seedDefaultPlan(): void
    {
        if (Plan::query()->exists()) {
            return;
        }

        $plan = Plan::create([
            'name' => 'پلن رایگان',
            'data_limit_bytes' => Bytes::fromGb((float) config('v2raybot.issuance.default_data_gb', 10)),
            'duration_days' => (int) config('v2raybot.issuance.default_duration_days', 30),
            'is_default' => true,
            'is_active' => true,
        ]);

        Setting::put(SettingKey::DEFAULT_PLAN_ID, $plan->id);
    }

    /** Example: every 5 verified referrals => +10 GB (inactive until admin enables). */
    protected function seedSampleReferralRule(): void
    {
        if (ReferralRule::query()->exists()) {
            return;
        }

        ReferralRule::create([
            'name' => 'هدیه‌ی دعوت دوستان',
            'mode' => ReferralRuleMode::Recurring,
            'threshold' => 5,
            'reward_type' => RewardType::Traffic,
            'reward_amount' => Bytes::fromGb(10),
            'is_active' => true,
        ]);
    }
}
