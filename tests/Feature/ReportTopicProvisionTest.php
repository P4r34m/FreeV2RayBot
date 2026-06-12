<?php

namespace Tests\Feature;

use App\Models\ReportTopic;
use App\Models\Setting;
use App\Services\ReportTopicProvisioner;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/**
 * The bot auto-creates one FreeBot-branded forum topic per report event, stores
 * the returned thread id, and is safe to re-run (skips topics already wired up).
 */
class ReportTopicProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_creates_freebot_named_topics_and_stores_thread_ids(): void
    {
        Setting::put(SettingKey::REPORTS_GROUP_ID, '-100123');

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        $events = array_keys(ReportTopic::defaults());
        $tid = 100;
        foreach ($events as $unused) {
            $bot->willReceive(['message_thread_id' => ++$tid, 'name' => 'x', 'icon_color' => 7322096]);
        }

        $result = app(ReportTopicProvisioner::class)->provision($bot, '-100123');

        $this->assertSame(count($events), $result['created']);
        $this->assertSame(0, $result['failed']);

        $topics = ReportTopic::all();
        $this->assertCount(count($events), $topics);
        foreach ($topics as $topic) {
            $this->assertNotNull($topic->thread_id);
            $this->assertStringStartsWith('FreeBot', $topic->title);
        }
    }

    public function test_provision_skips_topics_that_already_have_a_thread_id(): void
    {
        Setting::put(SettingKey::REPORTS_GROUP_ID, '-100123');

        foreach (array_keys(ReportTopic::defaults()) as $event) {
            ReportTopic::create([
                'event' => $event,
                'title' => ReportTopic::brandedName($event),
                'thread_id' => 555,
                'is_active' => true,
            ]);
        }

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class); // no willReceive queued: any API call would error

        $result = app(ReportTopicProvisioner::class)->provision($bot, '-100123');

        $this->assertSame(0, $result['created']);
        $this->assertSame(count(ReportTopic::defaults()), $result['skipped']);
    }

    public function test_provision_without_a_group_returns_an_error(): void
    {
        $result = app(ReportTopicProvisioner::class)->provision(app(Nutgram::class), '');

        $this->assertNotNull($result['error']);
        $this->assertSame(0, $result['created']);
    }

    public function test_branded_name_adds_the_prefix_only_when_missing(): void
    {
        $this->assertSame('FreeBot | کاربر جدید', ReportTopic::brandedName('new_user'));
        $this->assertSame('FreeBot | خطای سفارشی', ReportTopic::brandedName('error', 'خطای سفارشی'));
        $this->assertSame('FreeBot | از قبل', ReportTopic::brandedName('error', 'FreeBot | از قبل'));
    }
}
