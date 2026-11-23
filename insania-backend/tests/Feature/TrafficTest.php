<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** The System Admin's Security page: who reaches the server, and blocking addresses by hand. */
class TrafficTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private const CF_EDGE = '172.64.10.20';   // one of Cloudflare's own addresses

    private function at(string $ip, bool $cloudflare = false, string $country = 'PH'): self
    {
        $this->app['auth']->forgetGuards();

        return $cloudflare
            ? $this->withServerVariables(['REMOTE_ADDR' => self::CF_EDGE])->withHeaders(['CF-Connecting-IP' => $ip, 'CF-IPCountry' => $country])
            : $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['CF-Connecting-IP' => '', 'CF-IPCountry' => '']);
    }

    private function hit(string $ip): ?object
    {
        return DB::table('traffic_hits')->where('ip', $ip)->first();
    }

    public function test_requests_are_counted_by_address_with_the_country_only_from_cloudflare(): void
    {
        $this->world();
        $this->at('203.0.113.7', true)->getJson('/api/public/site')->assertOk();
        $this->at('203.0.113.7', true)->getJson('/api/public/apply')->assertOk();
        $h = $this->hit('203.0.113.7');
        $this->assertSame(2, (int) $h->requests);
        $this->assertSame('PH', $h->country);
        $this->assertSame(1, (int) DB::table('traffic_paths')->where('path', '/api/public/site')->value('requests'));

        // someone sending Cloudflare's headers straight to the server is not believed
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])->withHeaders(['CF-Connecting-IP' => '1.2.3.4', 'CF-IPCountry' => 'US'])->getJson('/api/public/site')->assertOk();
        $this->assertNull($this->hit('1.2.3.4'));
        $this->assertNull($this->hit('198.51.100.9')->country);

        // sign-ins and failed sign-ins are written down, with the account for a sign-in
        $this->at('203.0.113.8', true, 'SA')->postJson('/api/login', ['login' => 'aytam', 'password' => 'wrong'])->assertStatus(401);
        $this->at('203.0.113.8', true, 'SA')->postJson('/api/login', ['login' => 'aytam', 'password' => DemoSeeder::PASSWORD])->assertOk();
        $h = $this->hit('203.0.113.8');
        $this->assertSame([1, 1], [(int) $h->failed_logins, (int) $h->signins]);
        $this->assertSame(['login_failed', 'login'], DB::table('traffic_events')->where('ip', '203.0.113.8')->orderBy('id')->pluck('kind')->all());
        $this->assertNotNull(DB::table('traffic_events')->where('ip', '203.0.113.8')->where('kind', 'login')->value('user_id'));
    }

    public function test_detail_rows_are_capped_per_address_per_hour(): void
    {
        $this->world();
        for ($i = 0; $i < 70; $i++) {
            $this->at('192.0.2.50')->getJson('/api/nothing-here-'.$i)->assertNotFound();
        }
        $this->assertSame(70, (int) $this->hit('192.0.2.50')->requests);
        $this->assertSame(60, DB::table('traffic_events')->where('ip', '192.0.2.50')->count());
        $this->assertSame('not_found', DB::table('traffic_events')->where('ip', '192.0.2.50')->value('kind'));
    }

    public function test_only_the_system_admin_sees_the_security_page(): void
    {
        $this->world();
        $this->at('203.0.113.7', true)->getJson('/api/public/site');
        foreach (['president_admin', 'security_admin', 'admin_maintenance', 'staff:aytam', 'user'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson('/api/system/traffic')->assertForbidden();
            $this->postJson('/api/system/traffic/blocks', ['ip' => '192.0.2.1'])->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('system_admin');
        $d = $this->getJson('/api/system/traffic?range=7d')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(1, $d['totals']['requests']);
        $this->assertContains('203.0.113.7', array_column($d['visitors'], 'ip'));
        $this->assertContains('PH', array_column($d['countries'], 'code'));
        $this->assertSame(7, count($d['series']));
        $this->assertTrue($d['daily']);
        $this->assertSame(['logging' => true, 'blocking' => true, 'retention_days' => 30], $d['settings']);
        $this->getJson('/api/system/traffic/visitors?q=203.0')->assertOk()->assertJsonPath('data.0.ip', '203.0.113.7');
        $a = $this->getJson('/api/system/traffic/address?ip=203.0.113.7')->assertOk()->json('data');
        $this->assertSame(['PH'], $a['countries']);
        $this->assertNull($a['block']);
    }

    public function test_blocking_by_hand_with_its_safety_checks(): void
    {
        $this->world();
        // an address a staff member signed in from
        $this->at('198.51.100.20')->postJson('/api/login', ['login' => 'finance', 'password' => DemoSeeder::PASSWORD])->assertOk();

        $sys = $this->actingAsRole('system_admin');
        $me = fn () => $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->withHeaders(['CF-Connecting-IP' => '', 'CF-IPCountry' => '']);
        $me()->postJson('/api/system/traffic/blocks', ['ip' => '10.9.9.9'])->assertStatus(422)->assertJsonPath('code', 'OWN_ADDRESS');
        $me()->postJson('/api/system/traffic/blocks', ['ip' => '104.16.0.1'])->assertStatus(422)->assertJsonPath('code', 'CLOUDFLARE_ADDRESS');
        $me()->postJson('/api/system/traffic/blocks', ['ip' => 'not an address'])->assertStatus(422);
        $me()->postJson('/api/system/traffic/blocks', ['ip' => '198.51.100.20'])->assertStatus(409)->assertJsonPath('code', 'IP_IN_USE');
        $me()->postJson('/api/system/traffic/blocks', ['ip' => '198.51.100.20', 'reason' => 'Testing', 'confirm' => true])->assertCreated();
        $me()->postJson('/api/system/traffic/blocks', ['ip' => '192.0.2.66', 'days' => 1])->assertCreated();
        $this->assertSame(1, AuditLog::where('action', 'traffic.blocked')->where('meta->ip', '192.0.2.66')->count());

        // refused before anything else: sign-in, signed-in pages and public pages
        $this->at('192.0.2.66')->postJson('/api/login', ['login' => 'aytam', 'password' => DemoSeeder::PASSWORD])->assertStatus(403)->assertJsonPath('code', 'BLOCKED');
        $this->at('192.0.2.66')->getJson('/api/public/site')->assertStatus(403);
        $this->at('198.51.100.20')->postJson('/api/login', ['login' => 'finance', 'password' => DemoSeeder::PASSWORD])->assertStatus(403);
        $this->assertSame(3, DB::table('traffic_events')->where('kind', 'blocked')->count());
        $this->at('192.0.2.67')->getJson('/api/public/site')->assertOk();   // other addresses are fine

        // a block for a day ends by itself
        $this->travel(25)->hours();
        \Illuminate\Support\Facades\Cache::flush();
        $this->at('192.0.2.66')->getJson('/api/public/site')->assertOk();
        $this->travelBack();

        // unblock
        $this->app['auth']->forgetGuards();
        $this->actingAs($sys, 'sanctum');
        $id = DB::table('blocked_ips')->where('ip', '198.51.100.20')->value('id');
        $me()->deleteJson('/api/system/traffic/blocks/'.$id)->assertOk();
        $this->at('198.51.100.20')->getJson('/api/public/site')->assertOk();

        // the President never sees this in the activity log
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $this->assertSame([], array_values(array_filter(array_column($this->getJson('/api/audit?per_page=100')->json('data'), 'action'), fn ($a) => str_starts_with($a, 'traffic.'))));
    }

    public function test_the_system_admins_trusted_device_is_never_locked_out(): void
    {
        $this->world();
        $login = $this->at('192.0.2.80')->postJson('/api/login', ['login' => 'system@insania.test', 'password' => DemoSeeder::PASSWORD])->assertOk()->json('data');
        $this->app['auth']->forgetGuards();
        $secret = $this->at('192.0.2.80')->postJson('/api/me/devices/new', [], ['Authorization' => 'Bearer '.$login['token']])->assertCreated()->json('data.device_token');
        DB::table('blocked_ips')->insert(['ip' => '192.0.2.80', 'created_at' => now(), 'updated_at' => now()]);
        \App\Services\Traffic::forgetBlocks();

        $this->at('192.0.2.80')->getJson('/api/system/traffic/blocks', ['Authorization' => 'Bearer '.$login['token']])->assertStatus(403);
        $this->at('192.0.2.80')->getJson('/api/system/traffic/blocks', ['Authorization' => 'Bearer '.$login['token'], 'X-Device-Token' => $secret])->assertOk();
    }

    public function test_settings_switch_blocking_and_logging_and_old_rows_are_deleted(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');
        $this->patchJson('/api/system/traffic/settings', ['retention_days' => 3])->assertStatus(422);
        DB::table('blocked_ips')->insert(['ip' => '192.0.2.90', 'created_at' => now(), 'updated_at' => now()]);
        \App\Services\Traffic::forgetBlocks();
        $this->at('192.0.2.90')->getJson('/api/public/site')->assertStatus(403);

        $this->at('10.9.9.9');   // the System Admin, from another address
        $this->actingAsRole('system_admin');
        $this->patchJson('/api/system/traffic/settings', ['blocking' => false, 'logging' => false])->assertOk()->assertJsonPath('data.settings.blocking', false);
        $this->at('192.0.2.90')->getJson('/api/public/site')->assertOk();
        $before = DB::table('traffic_hits')->sum('requests');
        $this->at('192.0.2.91')->getJson('/api/public/site')->assertOk();
        $this->assertSame((int) $before, (int) DB::table('traffic_hits')->sum('requests'), 'logging off: nothing is counted');

        // older than the retention period: deleted
        $this->at('10.9.9.9');
        $this->actingAsRole('system_admin');
        $this->patchJson('/api/system/traffic/settings', ['logging' => true, 'blocking' => true])->assertOk();
        $old = now()->subDays(40)->startOfHour()->format('Y-m-d H:i:s');
        DB::table('traffic_hits')->insert(['hour' => $old, 'ip' => '192.0.2.99', 'requests' => 5, 'first_at' => $old, 'last_at' => $old]);
        DB::table('traffic_events')->insert(['at' => $old, 'ip' => '192.0.2.99', 'kind' => 'not_found', 'method' => 'GET', 'path' => '/x', 'status' => 404]);
        \Illuminate\Support\Facades\Cache::forget('traffic-pruned');
        $this->at('192.0.2.92')->getJson('/api/public/site')->assertOk();
        $this->assertNull($this->hit('192.0.2.99'));
        $this->assertSame(0, DB::table('traffic_events')->where('ip', '192.0.2.99')->count());
    }
}
