<?php

namespace Tests\Unit\Panels;

use App\Models\Panel;
use App\Panels\Data\ConfigSpec;
use App\Panels\Drivers\ThreeXuiDriver;
use App\Support\Bytes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 3x-ui v3.3.1 attaches one client to any number of inbounds in a single
 * clients/add call via the `inboundIds` list — there is no separate endpoint
 * for the single- vs multi-inbound case anymore.
 */
class ThreeXuiMultiInboundTest extends TestCase
{
    private function panel(array $settings): Panel
    {
        $panel = new Panel;
        $panel->id = 8;
        $panel->base_url = 'https://x.example.com:2053';
        $panel->settings = array_merge(['verify_ssl' => false, 'api_token' => 'tok'], $settings);

        return $panel;
    }

    public function test_multiple_inbounds_post_clients_add_with_id_list(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);

        (new ThreeXuiDriver($this->panel(['inbound_ids' => [1, 2]])))
            ->createConfig(new ConfigSpec(dataLimitBytes: Bytes::GB, expirySeconds: 3600, identifier: 'u1'));

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/panel/api/clients/add')) {
                return false;
            }
            $this->assertSame([1, 2], $r->data()['inboundIds']);
            $this->assertIsArray($r->data()['client']); // real nested object, no JSON-string quirk

            return true;
        });
    }

    public function test_single_inbound_uses_the_same_clients_add_endpoint(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);

        (new ThreeXuiDriver($this->panel(['inbound_id' => 5])))
            ->createConfig(new ConfigSpec(dataLimitBytes: Bytes::GB, identifier: 'u1'));

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/panel/api/clients/add')) {
                return false;
            }
            $this->assertSame([5], $r->data()['inboundIds']);

            return true;
        });
    }
}
