<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use App\Services\Cloudflare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Out;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Security page, phase 2: blocks at Cloudflare too, Cloudflare's own figures, and alerts (Cloudflare's API is faked). */
class CloudflareSecurityTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private const ZONE = 'aaaabbbbccccddddeeeeffff00001111';
    private const ACC = '99998888777766665555444433332222';

    private bool $refuse = false;
    private bool $refuseDelete = false;
    private bool $shortHistory = false;
    private array $sent = [];

    private function fake(): void
    {
        $ok = fn ($result) => Http::response(['success' => true, 'errors' => [], 'result' => $result]);
        $no = fn (string $m) => Http::response(['success' => false, 'errors' => [['message' => $m]]], 403);
        Http::fake(function (Out $r) use ($ok, $no) {
            $u = $r->url();
            $this->sent[] = [$r->method(), $u, $r->data()];
            if (str_ends_with($u, '/graphql')) {
                $q = (string) ($r->data()['query'] ?? '');
                $since = (string) ($r->data()['variables']['since'] ?? '');
                if (str_contains($q, 'httpRequests1dGroups')) {
                    return Http::response(['data' => ['viewer' => ['zones' => [['days' => [
                        ['dimensions' => ['date' => now()->subDay()->toDateString()], 'sum' => ['requests' => 900, 'pageViews' => 300, 'threats' => 12, 'countryMap' => [['clientCountryName' => 'PH', 'requests' => 800, 'threats' => 2], ['clientCountryName' => 'US', 'requests' => 100, 'threats' => 10]]], 'uniq' => ['uniques' => 120]],
                        ['dimensions' => ['date' => now()->toDateString()], 'sum' => ['requests' => 100, 'pageViews' => 40, 'threats' => 3, 'countryMap' => [['clientCountryName' => 'PH', 'requests' => 100, 'threats' => 3]]], 'uniq' => ['uniques' => 30]],
                    ]]]]], 'errors' => null]);
                }
                // free plans: the detail questions cannot go back a whole week
                if ($this->shortHistory && $since < now()->subHours(25)->utc()->format('Y-m-d\TH:i:s\Z')) {
                    return Http::response(['data' => null, 'errors' => [['message' => 'cannot request data older than 86400s']]]);
                }
                if (str_contains($q, 'httpRequestsAdaptiveGroups')) {
                    return Http::response(['data' => ['viewer' => ['zones' => [[
                        'paths' => [['count' => 640, 'dimensions' => ['clientRequestPath' => '/']], ['count' => 90, 'dimensions' => ['clientRequestPath' => '/wp-login.php']]],
                        'ips' => [['count' => 300, 'dimensions' => ['clientIP' => '203.0.113.7', 'clientCountryName' => 'PH']]],
                    ]]]]]);
                }
                if (str_contains($q, 'firewallEventsAdaptive')) {
                    return Http::response(['data' => ['viewer' => ['zones' => [['events' => [
                        ['action' => 'block', 'source' => 'ip', 'clientIP' => '192.0.2.66', 'clientCountryName' => 'US', 'clientRequestHTTPMethodName' => 'POST', 'clientRequestPath' => '/server/api/login', 'datetime' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'userAgent' => 'python-requests/2.31'],
                        ['action' => 'managed_challenge', 'source' => 'botFight', 'clientIP' => '198.51.100.3', 'clientCountryName' => 'DE', 'clientRequestHTTPMethodName' => 'GET', 'clientRequestPath' => '/', 'datetime' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'userAgent' => 'curl/8'],
                    ]]]]]]);
                }
            }
            if (str_contains($u, '/firewall/access_rules/rules')) {
                if ($r->method() === 'POST') {
                    return $this->refuse ? $no('Authentication error') : $ok(['id' => 'rule-'.substr(md5(json_encode($r->data())), 0, 8)]);
                }
                if ($r->method() === 'DELETE') {
                    return $this->refuseDelete ? $no('Authentication error') : $ok(['id' => basename(parse_url($u, PHP_URL_PATH))]);
                }

                return $ok([]);
            }
            if (str_ends_with($u, '/zones/'.self::ZONE)) {
                return $ok(['id' => self::ZONE, 'name' => 'alinsania.com', 'account' => ['id' => self::ACC, 'name' => 'Acf']]);
            }

            return $ok([]);
        });
    }

    private function connect(): void
    {
        $sys = User::where('email', $this->accounts['system_admin'])->firstOrFail();
        Cloudflare::save((int) $sys->foundation_id, 'cf-test-token-1234567890abcdefghij', self::ZONE, 'alinsania.com');
    }

    private function asSystem(): self
    {
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->withHeaders(['CF-Connecting-IP' => '', 'CF-IPCountry' => '']);
        $this->actingAsRole('system_admin');

        return $this;
    }

    private function sentTo(string $method, string $part): array
    {
        return array_values(array_filter($this->sent, fn ($s) => $s[0] === $method && str_contains($s[1], $part)));
    }

    public function test_a_block_is_made_at_cloudflare_too_and_removed_with_it(): void
    {
        $this->world();
        $this->fake();
        $this->connect();
        $this->asSystem()->postJson('/api/system/traffic/blocks', ['ip' => '192.0.2.66', 'reason' => 'Guessing'])->assertCreated();
        $this->asSystem()->postJson('/api/system/traffic/blocks', ['ip' => '2001:db8::5'])->assertCreated();
        $posts = $this->sentTo('POST', '/accounts/'.self::ACC.'/firewall/access_rules/rules');
        $this->assertSame(['mode' => 'block', 'target' => 'ip', 'value' => '192.0.2.66'], ['mode' => $posts[0][2]['mode'], 'target' => $posts[0][2]['configuration']['target'], 'value' => $posts[0][2]['configuration']['value']]);
        $this->assertSame('ip6', $posts[1][2]['configuration']['target']);
        $b = DB::table('blocked_ips')->where('ip', '192.0.2.66')->first();
        $this->assertSame('ok', $b->cf_status);
        $this->assertNotEmpty($b->cf_rule_id);
        $list = $this->asSystem()->getJson('/api/system/traffic/blocks')->json('data.blocks');
        $this->assertSame('ok', collect($list)->firstWhere('ip', '192.0.2.66')['cloudflare']['status']);

        $this->asSystem()->deleteJson('/api/system/traffic/blocks/'.$b->id)->assertOk()->assertJsonPath('data.warning', null);
        $this->assertCount(1, $this->sentTo('DELETE', '/firewall/access_rules/rules/'.$b->cf_rule_id));
        $this->assertFalse(DB::table('blocked_ips')->where('ip', '192.0.2.66')->exists());
    }

    public function test_if_cloudflare_refuses_the_app_still_blocks_and_can_try_again(): void
    {
        $this->world();
        $this->fake();
        $this->connect();
        $this->refuse = true;
        $this->asSystem()->postJson('/api/system/traffic/blocks', ['ip' => '192.0.2.70'])->assertCreated();
        $b = DB::table('blocked_ips')->where('ip', '192.0.2.70')->first();
        $this->assertSame(['failed', 'Authentication error'], [$b->cf_status, $b->cf_error]);
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.70'])->getJson('/api/public/site')->assertStatus(403);   // the app's own block works

        $this->asSystem()->postJson('/api/system/traffic/blocks/'.$b->id.'/cloudflare')->assertStatus(422);
        $this->refuse = false;
        $this->asSystem()->postJson('/api/system/traffic/blocks/'.$b->id.'/cloudflare')->assertOk();
        $this->assertSame('ok', DB::table('blocked_ips')->where('id', $b->id)->value('cf_status'));

        // unblocking while Cloudflare refuses: let in by the app now, removed at Cloudflare later
        $this->refuseDelete = true;
        $res = $this->asSystem()->deleteJson('/api/system/traffic/blocks/'.$b->id)->assertOk();
        $this->assertStringContainsString('Cloudflare did not remove it yet', (string) $res->json('data.warning'));
        $this->assertSame('removing', DB::table('blocked_ips')->where('id', $b->id)->value('cf_status'));
        Cache::flush();
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.70'])->getJson('/api/public/site')->assertOk();
        $this->refuseDelete = false;
        Cache::forget('traffic-maintained');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.71'])->getJson('/api/public/site')->assertOk();   // any request runs the upkeep
        $this->assertFalse(DB::table('blocked_ips')->where('id', $b->id)->exists());
    }

    public function test_a_block_that_ends_is_removed_at_cloudflare(): void
    {
        $this->world();
        $this->fake();
        $this->connect();
        $this->asSystem()->postJson('/api/system/traffic/blocks', ['ip' => '192.0.2.80', 'days' => 1])->assertCreated();
        $rule = DB::table('blocked_ips')->where('ip', '192.0.2.80')->value('cf_rule_id');
        $this->travel(25)->hours();
        Cache::flush();
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.81'])->getJson('/api/public/site')->assertOk();
        $this->assertCount(1, $this->sentTo('DELETE', '/firewall/access_rules/rules/'.$rule));
        $this->assertSame([null, 'removed'], [DB::table('blocked_ips')->where('ip', '192.0.2.80')->value('cf_rule_id'), DB::table('blocked_ips')->where('ip', '192.0.2.80')->value('cf_status')]);
    }

    public function test_without_cloudflare_nothing_is_sent(): void
    {
        $this->world();
        $this->fake();
        $this->asSystem()->postJson('/api/system/traffic/blocks', ['ip' => '192.0.2.90'])->assertCreated();
        $this->assertNull(DB::table('blocked_ips')->where('ip', '192.0.2.90')->value('cf_status'));
        $this->assertSame(['connected' => false], $this->asSystem()->getJson('/api/system/traffic/cloudflare')->assertOk()->json('data'));
        $this->assertSame([], $this->sentTo('POST', 'cloudflare.com'));
    }

    public function test_cloudflares_figures_with_a_shorter_history_on_small_plans(): void
    {
        $this->world();
        $this->fake();
        $this->connect();
        $this->shortHistory = true;
        $d = $this->asSystem()->getJson('/api/system/traffic/cloudflare?range=7d')->assertOk()->json('data');
        $this->assertSame(['requests' => 1000, 'page_views' => 340, 'visitors' => 150, 'threats' => 15], $d['totals']);
        $this->assertSame(['PH', 'US'], array_column($d['countries'], 'code'));
        $this->assertSame(900, $d['countries'][0]['requests']);
        $this->assertCount(7, $d['series']);
        $this->assertSame(1000, array_sum(array_column($d['series'], 'requests')));
        $this->assertSame('/wp-login.php', $d['paths'][1]['path']);
        $this->assertSame('203.0.113.7', $d['ips'][0]['ip']);
        $this->assertSame(['managed_challenge', 'botFight'], [$d['stopped'][1]['action'], $d['stopped'][1]['source']]);
        $this->assertSame(['top' => 'last_24h', 'stopped' => 'last_24h'], $d['notes']);
        $this->assertSame([], $d['errors']);

        // kept five minutes; "fresh" asks again
        $n = count($this->sentTo('POST', '/graphql'));
        $this->asSystem()->getJson('/api/system/traffic/cloudflare?range=7d')->assertOk();
        $this->assertSame($n, count($this->sentTo('POST', '/graphql')));
        $this->asSystem()->getJson('/api/system/traffic/cloudflare?range=7d&fresh=1')->assertOk();
        $this->assertGreaterThan($n, count($this->sentTo('POST', '/graphql')));
        $this->asSystem()->getJson('/api/system/traffic')->assertOk()->assertJsonPath('data.cloudflare', true);

        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $this->getJson('/api/system/traffic/cloudflare')->assertForbidden();
    }

    public function test_alerts_tell_the_system_admin_once_and_never_block(): void
    {
        $this->world();
        $sys = User::where('email', $this->accounts['system_admin'])->firstOrFail();
        $pres = User::where('email', $this->accounts['president_admin'])->firstOrFail();
        for ($i = 1; $i <= 12; $i++) {   // different names, so the per-name sign-in limit does not stop it first
            $this->app['auth']->forgetGuards();
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->postJson('/api/login', ['login' => 'nobody'.$i, 'password' => 'wrong'])->assertStatus(401);
        }
        $n = Notice::where('user_id', $sys->id)->where('type', 'security')->get();
        $this->assertCount(1, $n, 'once, at 10 wrong passwords');
        $this->assertStringContainsString('192.0.2.99', $n[0]->body);
        $this->assertSame('security.html?ip=192.0.2.99', $n[0]->url);
        $this->assertSame(0, Notice::where('user_id', $pres->id)->where('type', 'security')->count());
        $this->assertFalse(DB::table('blocked_ips')->where('ip', '192.0.2.99')->exists(), 'nothing is blocked by itself');

        // refused or unknown requests, with a lower threshold; then alerts off
        $this->asSystem()->patchJson('/api/system/traffic/settings', ['alert_refused' => 20])->assertOk();
        $this->asSystem()->patchJson('/api/system/traffic/settings', ['alert_failed' => 7])->assertStatus(422);
        for ($i = 0; $i < 20; $i++) {
            $this->app['auth']->forgetGuards();
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.98'])->getJson('/api/no-such-page-'.$i)->assertNotFound();
        }
        $this->assertSame(1, Notice::where('user_id', $sys->id)->where('title', 'Many refused requests from one address')->count());
        $this->asSystem()->patchJson('/api/system/traffic/settings', ['alerts' => false])->assertOk();
        for ($i = 0; $i < 20; $i++) {
            $this->app['auth']->forgetGuards();
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.97'])->getJson('/api/no-such-page-'.$i)->assertNotFound();
        }
        $this->assertSame(2, Notice::where('user_id', $sys->id)->where('type', 'security')->count());
    }
}
