<?php

namespace Tests\Feature;

use App\Models\BotUser;
use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/** A new user starting via a referral deep link must get a reply and be linked. */
class ReferralStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_start_with_ref_link_responds_and_links(): void
    {
        $referrer = BotUser::create(['telegram_id' => 555]);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->hearMessage([
            'from' => ['id' => 777, 'is_bot' => false, 'first_name' => 'B'],
            'text' => '/start ref_555',
        ])->reply();

        $bot->assertCalled('sendMessage'); // the welcome menu must be sent
        $this->assertSame($referrer->id, BotUser::where('telegram_id', 777)->first()?->referred_by);
    }

    public function test_ref_link_start_shows_the_referred_welcome(): void
    {
        BotUser::create(['telegram_id' => 900]);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->hearMessage([
            'from' => ['id' => 901, 'is_bot' => false, 'first_name' => 'D'],
            'text' => '/start ref_900',
        ])->reply();

        $bot->assertReplyText(\App\Telegram\Content::text('welcome_referred'), 0);
    }

    public function test_plain_start_shows_the_normal_welcome(): void
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->hearMessage([
            'from' => ['id' => 902, 'is_bot' => false, 'first_name' => 'E'],
            'text' => '/start',
        ])->reply();

        $bot->assertReplyText(\App\Telegram\Content::text('welcome'), 0);
    }

    public function test_new_user_start_with_ref_link_in_coin_mode_grants_coins_on_start(): void
    {
        Setting::put(SettingKey::REFERRAL_MODE, 'coin');
        Setting::put(SettingKey::REFERRAL_COINS_PER_INVITE, 5);
        Setting::put(SettingKey::REFERRAL_QUALIFY_EVENT, 'start');

        $referrer = BotUser::create(['telegram_id' => 600]);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->hearMessage([
            'from' => ['id' => 601, 'is_bot' => false, 'first_name' => 'C'],
            'text' => '/start ref_600',
        ])->reply();

        $bot->assertCalled('sendMessage');
        $this->assertSame(5, $referrer->fresh()->coins);
    }
}
