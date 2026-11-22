<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Out;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** The System Admin switches Cloudflare protections from the app (Cloudflare's API is faked here). */
class CloudflareControlTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private const ZONE = 'aaaabbbbccccddddeeeeffff00001111';
    private const RULE = '11112222333344445555666677778888';
    private const TOKEN = 'cf-test-token-1234567890abcdefghij';

    private bool $deny = false;

    private function fakeCloudflare(array &$sent = []): void
    {
        $ok = fn ($result) => Http::response(['success' => true, 'errors' => [], 'result' => $result]);
        Http::fake(function (Out $r) use (&$sent, $ok) {
            $sent[] = [$r->method(), $r->url(), $r->data()];
            $u = $r->url();
            if ($this->deny) {
                return Http::response(['success' => false, 'errors' => [['message' => 'Authentication error']]], 403);
            }
            if (str_contains($u, '/zones?per_page=')) {
                return $ok([['id' => self::ZONE, 'name' => 'alinsania.com']]);
            }
            if (str_contains($u, '/zones?name=')) {
                return $ok([['id' => self::ZONE, 'name' => 'alinsania.com']]);
            }
            if (str_contains($u, '/settings/security_level')) {
                return $ok(['value' => 'medium']);
            }
            if (str_contains($u, '/bot_management')) {
                return $ok(['fight_mode' => true]);
            }
            if (str_contains($u, '/leaked-credential-checks')) {
                return $ok(['enabled' => true]);
            }
            if (str_contains($u, '/rulesets/phases/http_ratelimit/entrypoint')) {
                return $ok(['id' => 'rs1', 'rules' => [['id' => self::RULE, 'description' => 'Leaked credentials limit', 'action' => 'block', 'expression' => '(cf.waf.credential_check.password_leaked)', 'enabled' => true, 'ratelimit' => ['period' => 10, 'requests_per_period' => 5], 'version' => '3', 'last_updated' => 'x']]]);
            }
            if (str_contains($u, '/rulesets/phases/http_request_firewall_custom/entrypoint')) {
                return Http::response(['success' => false, 'errors' => [['message' => 'could not find entrypoint ruleset']]], 404);
            }
            if (str_contains($u, '/rulesets/rs1/rules/')) {
                return $ok(['id' => 'rs1']);
            }

            return $ok(['id' => self::ZONE, 'name' => 'alinsania.com', 'paused' => false, 'status' => 'active']);
        });
    }

    public function test_only_the_system_admin_can_use_it(): void
    {
        $this->world();
        foreach (['security_admin', 'president_admin', 'staff:aytam', 'admin_maintenance', 'user'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson('/api/system/cloudflare')->assertForbidden();
            $this->patchJson('/api/system/cloudflare', ['paused' => true])->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('system_admin');
        $this->getJson('/api/system/cloudflare')->assertOk()->assertJsonPath('data.connected', false);
    }

    public function test_connect_needs_the_password_and_keeps_the_token_secret(): void
    {
        $this->world();
        $this->fakeCloudflare();
        $this->actingAsRole('system_admin');
        $this->postJson('/api/system/cloudflare', ['api_token' => self::TOKEN, 'admin_password' => 'not my password'])->assertStatus(422)->assertJsonPath('code', 'WRONG_PASSWORD');
        $r = $this->postJson('/api/system/cloudflare', ['api_token' => self::TOKEN, 'admin_password' => 'DemoPass#2026'])->assertOk();
        $r->assertJsonPath('data.connected', true)->assertJsonPath('data.zone.name', 'alinsania.com')->assertJsonPath('data.security_level', 'medium')->assertJsonPath('data.bot_fight', true)->assertJsonPath('data.leaked', true);
        $this->assertSame('Leaked credentials limit', $r->json('data.rules.0.name'));
        $this->assertStringNotContainsString(self::TOKEN, $r->getContent());
        $this->assertStringNotContainsString(self::TOKEN, $this->getJson('/api/system/cloudflare')->getContent());
        $stored = DB::table('settings')->where('key', 'cloudflare_token')->value('value');
        $this->assertNotNull($stored);
        $this->assertStringNotContainsString(self::TOKEN, $stored, 'the token is stored encrypted');
        $this->assertDatabaseHas('audit_logs', ['action' => 'cloudflare.connected']);
    }

    public function test_switches_send_the_right_calls(): void
    {
        $this->world();
        $sent = [];
        $this->fakeCloudflare($sent);
        $this->actingAsRole('system_admin');
        $this->postJson('/api/system/cloudflare', ['api_token' => self::TOKEN, 'admin_password' => 'DemoPass#2026', 'zone_id' => self::ZONE])->assertOk();
        $sent = [];
        $this->patchJson('/api/system/cloudflare', ['paused' => true, 'security_level' => 'under_attack', 'bot_fight' => false, 'leaked' => false])->assertOk();
        $find = function (string $m, string $part) use (&$sent) {
            return collect($sent)->first(fn ($s) => $s[0] === $m && str_contains($s[1], $part));
        };
        $this->assertTrue($find('PATCH', '/zones/'.self::ZONE)[2]['paused']);
        $this->assertSame('under_attack', $find('PATCH', '/settings/security_level')[2]['value']);
        $this->assertFalse($find('PUT', '/bot_management')[2]['fight_mode']);
        $this->assertFalse($find('POST', '/leaked-credential-checks')[2]['enabled']);
        $this->patchJson('/api/system/cloudflare', ['security_level' => 'sky_high'])->assertStatus(422);

        $sent = [];
        $this->patchJson('/api/system/cloudflare/rules/'.self::RULE, ['enabled' => false, 'phase' => 'http_ratelimit'])->assertOk();
        $call = $find('PATCH', '/rulesets/rs1/rules/'.self::RULE);
        $this->assertFalse($call[2]['enabled']);
        $this->assertSame('block', $call[2]['action']);
        $this->assertArrayHasKey('ratelimit', $call[2]);
        $this->assertArrayNotHasKey('version', $call[2]);
        $this->patchJson('/api/system/cloudflare/rules/ffffffffffffffffffffffffffffffff', ['enabled' => false, 'phase' => 'http_ratelimit'])->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'cloudflare.rule']);
    }

    public function test_a_refusal_from_cloudflare_is_shown_plainly_and_disconnect_forgets_everything(): void
    {
        $this->world();
        $this->fakeCloudflare();
        $this->actingAsRole('system_admin');
        $this->postJson('/api/system/cloudflare', ['api_token' => self::TOKEN, 'admin_password' => 'DemoPass#2026'])->assertOk();
        $this->deny = true;
        $this->patchJson('/api/system/cloudflare', ['paused' => true])->assertStatus(422)->assertJsonPath('errors.cloudflare.0', 'Authentication error');
        $this->deleteJson('/api/system/cloudflare')->assertOk();
        $this->assertSame(0, Setting::withoutGlobalScopes()->where('key', 'like', 'cloudflare%')->count());
        $this->getJson('/api/system/cloudflare')->assertOk()->assertJsonPath('data.connected', false);
    }
}
