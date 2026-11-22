<?php

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class TrustedDeviceTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
        Cache::flush();
    }

    private function signIn(array $extra = [], array $headers = [])
    {
        return $this->withHeaders($headers)->postJson('/api/login', ['login' => 'aytam@insania.test', 'password' => 'DemoPass#2026'] + $extra);
    }

    public function test_trusting_at_sign_in_returns_a_secret_and_stores_only_its_hash(): void
    {
        $r = $this->signIn(["trust_device" => true])->assertOk();
        $secret = $r->json('data.device_token');
        $this->assertNotEmpty($secret);
        $this->assertSame(1, TrustedDevice::count());
        $this->assertNotSame($secret, TrustedDevice::first()->token_hash);
        $this->assertSame(hash('sha256', $secret), TrustedDevice::first()->token_hash);
        $this->assertNull($this->signIn()->json('data.device_token'));                      // not asked: nothing is trusted
        $this->assertSame(1, TrustedDevice::count());
    }

    public function test_a_trusted_device_is_not_slowed_down_by_the_request_limits(): void
    {
        $secret = $this->signIn(['trust_device' => true])->json('data.device_token');
        $token = $this->signIn()->json('data.token');
        // an ordinary device runs out after 300 requests a minute; the trusted one does not
        $this->withToken($token);
        for ($i = 0; $i < 305; $i++) {
            $code = $this->withHeaders([\App\Support\TrustedDevices::HEADER => $secret])->getJson('/api/notices/count')->status();
            if ($code !== 200) {
                $this->fail("trusted device was limited at request $i ($code)");
            }
        }
        $this->assertTrue(true);
    }

    public function test_an_ordinary_device_still_has_the_limit(): void
    {
        $token = $this->signIn()->json('data.token');
        $hit = null;
        for ($i = 0; $i < 305; $i++) {
            if ($this->withToken($token)->getJson('/api/notices/count')->status() === 429) {
                $hit = $i;
                break;
            }
        }
        $this->assertNotNull($hit, 'an untrusted device must still be limited');
    }

    public function test_a_secret_of_another_account_or_a_made_up_one_does_not_help(): void
    {
        $secret = $this->signIn(['trust_device' => true])->json('data.device_token');
        $other = $this->withHeaders([])->postJson('/api/login', ['login' => 'finance@insania.test', 'password' => 'DemoPass#2026'])->json('data.token');
        $hit = false;
        for ($i = 0; $i < 305 && ! $hit; $i++) {
            $hit = $this->withToken($other)->withHeaders([\App\Support\TrustedDevices::HEADER => $secret])->getJson('/api/notices/count')->status() === 429;
        }
        $this->assertTrue($hit, "another account's device secret must not lift the limit");
        Cache::flush();
        $hit = false;
        for ($i = 0; $i < 305 && ! $hit; $i++) {
            $hit = $this->withToken($other)->withHeaders([\App\Support\TrustedDevices::HEADER => str_repeat('a', 48)])->getJson('/api/notices/count')->status() === 429;
        }
        $this->assertTrue($hit, 'a made up secret must not lift the limit');
    }

    public function test_the_sign_in_limit_is_lifted_for_a_trusted_device_but_wrong_passwords_still_lock(): void
    {
        $secret = $this->signIn(['trust_device' => true])->json('data.device_token');
        for ($i = 0; $i < 40; $i++) {
            $code = $this->withHeaders([\App\Support\TrustedDevices::HEADER => $secret])->postJson('/api/login', ['login' => 'zzz'.$i.'@insania.test', 'password' => 'x'])->status();
            $this->assertNotSame(429, $code, 'the broad sign-in limit must not apply to a trusted device (attempt '.$i.')');
        }
        // wrong passwords on one account still lock it, trusted or not
        Cache::flush();
        $locked = false;
        for ($i = 0; $i < 12 && ! $locked; $i++) {
            $locked = $this->withHeaders([\App\Support\TrustedDevices::HEADER => $secret])->postJson('/api/login', ['login' => 'aytam@insania.test', 'password' => 'Wrong#Pass12345'])->status() === 429;
        }
        $this->assertTrue($locked, 'repeated wrong passwords must still lock the account');
    }

    public function test_trust_can_be_added_from_settings_with_the_password_listed_and_removed(): void
    {
        $token = $this->signIn()->json('data.token');
        $this->withToken($token)->postJson('/api/me/devices', ['password' => 'wrong'])->assertStatus(422);
        $secret = $this->withToken($token)->postJson('/api/me/devices', ['password' => 'DemoPass#2026'])->assertCreated()->json('data.device_token');
        $list = $this->withToken($token)->withHeaders([\App\Support\TrustedDevices::HEADER => $secret])->getJson('/api/me/devices')->assertOk()->json('data');
        $this->assertCount(1, $list['devices']);
        $this->assertTrue($list['devices'][0]['current']);
        $this->assertTrue($list['this_device']);
        $id = $list['devices'][0]['id'];
        $this->withToken($token)->deleteJson("/api/me/devices/$id")->assertOk();
        $this->assertSame(0, TrustedDevice::count());
        $this->withToken($token)->deleteJson('/api/me/devices/999999')->assertNotFound();
    }

    public function test_one_person_cannot_see_or_remove_anothers_devices(): void
    {
        $this->signIn(['trust_device' => true]);
        $id = TrustedDevice::first()->id;
        $other = $this->postJson('/api/login', ['login' => 'finance@insania.test', 'password' => 'DemoPass#2026'])->json('data.token');
        $this->withToken($other)->deleteJson("/api/me/devices/$id")->assertNotFound();
        $this->assertCount(0, $this->withToken($other)->getJson('/api/me/devices')->json('data.devices'));
    }

    public function test_changing_the_password_or_switching_the_account_off_ends_the_trust(): void
    {
        $user = User::where('email', 'aytam@insania.test')->first();
        $this->signIn(['trust_device' => true]);
        $token = $this->signIn()->json('data.token');
        $this->withToken($token)->postJson('/api/me/password', ['current_password' => 'DemoPass#2026', 'password' => 'Zebra!Pine7Sun42'])->assertOk();
        $this->assertSame(0, TrustedDevice::where('user_id', $user->id)->count());

        $secret = TrustedDevice::create(['user_id' => $user->id, 'token_hash' => hash('sha256', str_repeat('b', 48)), 'name' => 'x', 'expires_at' => now()->addDay()]);
        \App\Support\TrustedDevices::forgetAll($user->id);
        $this->assertSame(0, TrustedDevice::count());
    }

    public function test_an_expired_device_is_not_trusted(): void
    {
        $secret = $this->signIn(['trust_device' => true])->json('data.device_token');
        TrustedDevice::query()->update(['expires_at' => now()->subMinute()]);
        Cache::flush();
        $token = $this->signIn()->json('data.token');
        $hit = false;
        for ($i = 0; $i < 305 && ! $hit; $i++) {
            $hit = $this->withToken($token)->withHeaders([\App\Support\TrustedDevices::HEADER => $secret])->getJson('/api/notices/count')->status() === 429;
        }
        $this->assertTrue($hit);
    }
}
