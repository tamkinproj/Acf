<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** "Remember me" keeps a sign-in for days; after signing in on a device that is not trusted, the person sees its details and can trust it. */
class RememberAndNewDeviceTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; SM-S918B Build/UP1A.231005.007) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';

    private function signIn(string $email, bool $remember, array $headers = []): array
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/login', ['login' => $email, 'password' => DemoSeeder::PASSWORD, 'remember' => $remember], ['User-Agent' => self::ANDROID] + $headers)->assertOk()->json('data');
    }

    private function me(string $token, array $headers = []): int
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token] + $headers)->status();
    }

    public function test_remember_me_keeps_the_sign_in_for_days_and_without_it_hours(): void
    {
        $this->world();
        $short = $this->signIn('aytam@insania.test', false);
        $long = $this->signIn('aytam@insania.test', true);
        $this->assertFalse($short['remember']);
        $this->assertTrue($long['remember']);
        $this->assertEqualsWithDelta(now()->addHours(12)->timestamp, strtotime($short['expires_at']), 5);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, strtotime($long['expires_at']), 5);
        $pres = $this->signIn('president@insania.test', true);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, strtotime($pres['expires_at']), 5, 'the President, System and Security: 7 days');

        // 5 hours with no use: the usual sign-in ends (idle), the remembered one stays
        Carbon::setTestNow(now()->addHours(5));
        $this->assertSame(401, $this->me($short['token']));
        $this->assertSame(200, $this->me($long['token']));
        $this->assertSame(200, $this->me($pres['token']));
        // two weeks later: staff still signed in; the President's 7 days are over
        Carbon::setTestNow(now()->addDays(14));
        $this->assertSame(200, $this->me($long['token']));
        $this->assertSame(401, $this->me($pres['token']));
        Carbon::setTestNow(now()->addDays(17));
        $this->assertSame(401, $this->me($long['token']), 'after 30 days it ends');
        Carbon::setTestNow();
    }

    public function test_after_signing_in_a_new_device_shows_its_details_and_can_be_trusted(): void
    {
        $this->world();
        $d = $this->signIn('aytam@insania.test', true, ['CF-IPCountry' => 'PH']);
        $this->assertFalse($d['device']['trusted']);
        $this->assertSame('Android 14', $d['device']['system']);
        $this->assertSame('Chrome 124', $d['device']['browser']);
        $this->assertSame('SM-S918B', $d['device']['model']);
        $this->assertSame('PH', $d['device']['country']);
        $this->assertNotEmpty($d['device']['ip']);

        // trust it right away, without the password again
        $this->app['auth']->forgetGuards();
        $secret = $this->postJson('/api/me/devices/new', [], ['Authorization' => 'Bearer '.$d['token'], 'User-Agent' => self::ANDROID])->assertCreated()->json('data.device_token');
        $this->assertNotEmpty($secret);
        $again = $this->signIn('aytam@insania.test', false, ['X-Device-Token' => $secret]);
        $this->assertTrue($again['device']['trusted'], 'the next sign-in on this device knows it is trusted');

        // later than 15 minutes after signing in: Settings, with the password
        $late = $this->signIn('finance@insania.test', false);
        Carbon::setTestNow(now()->addMinutes(16));
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/me/devices/new', [], ['Authorization' => 'Bearer '.$late['token']])->assertStatus(422)->assertJsonPath('code', 'TOO_LATE');
        Carbon::setTestNow();
    }

    public function test_a_trusted_device_is_never_slowed_down_by_the_request_limits(): void
    {
        $this->world();
        $d = $this->signIn('aytam@insania.test', false);
        $this->app['auth']->forgetGuards();
        $secret = $this->postJson('/api/me/devices/new', [], ['Authorization' => 'Bearer '.$d['token']])->assertCreated()->json('data.device_token');

        // "Trust this device" in Settings allows 6 tries a minute; a trusted device is not counted
        $codes = [];
        for ($i = 0; $i < 9; $i++) {
            $this->app['auth']->forgetGuards();
            $codes[] = $this->postJson('/api/me/devices', ['password' => 'wrong'], ['Authorization' => 'Bearer '.$d['token'], 'X-Device-Token' => $secret])->status();
        }
        $this->assertSame(array_fill(0, 9, 422), $codes);

        // the same from a device that is not trusted stops at the limit
        $other = $this->signIn('finance@insania.test', false);
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $this->app['auth']->forgetGuards();
            $codes[] = $this->postJson('/api/me/devices', ['password' => 'wrong'], ['Authorization' => 'Bearer '.$other['token']])->status();
        }
        $this->assertContains(429, $codes);
    }
}
