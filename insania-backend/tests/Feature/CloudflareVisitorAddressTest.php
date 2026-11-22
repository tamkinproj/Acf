<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class CloudflareVisitorAddressTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function signInFrom(string $remote, ?string $cf): ?string
    {
        $headers = ['Accept' => 'application/json'];
        if ($cf !== null) {
            $headers['CF-Connecting-IP'] = $cf;
        }
        $this->withServerVariables(['REMOTE_ADDR' => $remote])->withHeaders($headers)
            ->postJson('/api/login', ['login' => 'aytam@insania.test', 'password' => 'DemoPass#2026'])->assertOk();

        return AuditLog::where('action', 'login')->latest('id')->value('ip');
    }

    public function test_behind_cloudflare_the_visitors_own_address_is_used(): void
    {
        $this->world();
        $this->assertSame('203.0.113.7', $this->signInFrom('173.245.48.10', '203.0.113.7'));          // IPv4 Cloudflare edge
        $this->assertSame('198.51.100.9', $this->signInFrom('2606:4700::1', '198.51.100.9'));          // IPv6 Cloudflare edge
    }

    public function test_the_header_is_ignored_when_the_request_does_not_come_from_cloudflare(): void
    {
        $this->world();
        $this->assertSame('192.0.2.50', $this->signInFrom('192.0.2.50', '203.0.113.7'));               // someone faking the header
        $this->assertSame('173.245.48.10', $this->signInFrom('173.245.48.10', 'not-an-address'));      // a broken header
        $this->assertSame('192.0.2.51', $this->signInFrom('192.0.2.51', null));                         // no Cloudflare at all
    }

    public function test_sign_in_limits_are_per_visitor_not_shared_through_cloudflare(): void
    {
        $this->world();
        // 30 sign-in attempts a minute per address: one visitor using them all up must not lock out another behind the same Cloudflare edge.
        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10'])->withHeaders(['CF-Connecting-IP' => '203.0.113.7'])
                ->postJson('/api/login', ['login' => 'nobody'.$i.'@insania.test', 'password' => 'x']);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10'])->withHeaders(['CF-Connecting-IP' => '203.0.113.7'])
            ->postJson('/api/login', ['login' => 'aytam@insania.test', 'password' => 'DemoPass#2026'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10'])->withHeaders(['CF-Connecting-IP' => '198.51.100.20'])
            ->postJson('/api/login', ['login' => 'aytam@insania.test', 'password' => 'DemoPass#2026'])->assertOk();
    }
}
