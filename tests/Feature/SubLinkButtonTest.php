<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Models\Config;
use App\Telegram\Keyboards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Tests\TestCase;

/** A glass URL button to open the subscription in the browser sits under the link. */
class SubLinkButtonTest extends TestCase
{
    use RefreshDatabase;

    private function urlButtons(InlineKeyboardMarkup $kb): Collection
    {
        return collect($kb->inline_keyboard)->flatten(1)->filter(fn ($b) => $b->url !== null);
    }

    public function test_after_issue_has_a_url_button_to_the_subscription(): void
    {
        $user = BotUser::create(['telegram_id' => 9100]);
        $config = $user->configs()->create([
            'source' => Config::SOURCE_COIN, 'remote_identifier' => 'fv_s',
            'subscription_url' => 'https://sub.example.com/abc', 'status' => ConfigStatus::Active,
        ]);

        $urlBtn = $this->urlButtons(Keyboards::afterIssue($config))->first();

        $this->assertNotNull($urlBtn);
        $this->assertSame('https://sub.example.com/abc', $urlBtn->url);
        $this->assertSame('🌐 مشاهده لینک اشتراک در سایت', $urlBtn->text);
    }

    public function test_after_issue_without_subscription_has_no_url_button(): void
    {
        $user = BotUser::create(['telegram_id' => 9101]);
        $config = $user->configs()->create([
            'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_n', 'status' => ConfigStatus::Active,
        ]);

        $this->assertCount(0, $this->urlButtons(Keyboards::afterIssue($config)));
    }
}
