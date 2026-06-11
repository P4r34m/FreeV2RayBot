<?php

namespace Tests\Feature;

use App\Services\ChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ChannelService
    {
        return app(ChannelService::class);
    }

    public function test_save_uses_admin_supplied_invite_link(): void
    {
        $channel = $this->service()->save([
            'chat_id' => '-1001234567890',
            'title' => 'My Private Channel',
            'username' => null,
            'is_private' => true,
        ], 'https://t.me/+AbCdEfGhIj');

        $this->assertSame('https://t.me/+AbCdEfGhIj', $channel->invite_link);
        $this->assertTrue($channel->is_private);
        $this->assertTrue($channel->is_active);
    }

    public function test_save_falls_back_to_username_link_when_none_given(): void
    {
        $channel = $this->service()->save([
            'chat_id' => '-1009876543210',
            'title' => 'Public',
            'username' => 'mychannel',
            'is_private' => false,
        ], null);

        $this->assertSame('https://t.me/mychannel', $channel->invite_link);
    }

    public function test_save_is_idempotent_per_chat_id(): void
    {
        $data = ['chat_id' => '-100111', 'title' => 'A', 'username' => null, 'is_private' => true];
        $this->service()->save($data, 'https://t.me/+one');
        $this->service()->save($data, 'https://t.me/+two');

        $this->assertDatabaseCount('required_channels', 1);
        $this->assertSame('https://t.me/+two', \App\Models\RequiredChannel::first()->invite_link);
    }
}
