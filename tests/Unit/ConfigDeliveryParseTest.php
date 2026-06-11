<?php

namespace Tests\Unit;

use App\Services\ConfigDeliveryService;
use Tests\TestCase;

class ConfigDeliveryParseTest extends TestCase
{
    /** Tiny subclass exposing the protected parse() method. */
    private function parse(string $body): array
    {
        $service = new class extends ConfigDeliveryService
        {
            public function parsePublic(string $body): array
            {
                return $this->parse($body);
            }
        };

        return $service->parsePublic($body);
    }

    public function test_base64_body_is_decoded_into_links(): void
    {
        $body = base64_encode("vless://a\nvmess://b");

        $this->assertSame(['vless://a', 'vmess://b'], $this->parse($body));
    }

    public function test_plain_non_base64_body_yields_its_link(): void
    {
        $this->assertSame(['trojan://x'], $this->parse('trojan://x'));
    }

    public function test_junk_body_yields_empty_array(): void
    {
        $this->assertSame([], $this->parse('this is not a config at all'));
    }
}
