<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function login(string $email = 'president@insania.test', string $password = DemoSeeder::PASSWORD)
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $password]);
    }

    public function test_login_returns_a_token_and_the_users_permissions(): void
    {
        $this->world();

        $res = $this->login()->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($res->json('data.token'));
        $this->assertContains('children.view', $res->json('data.user.permissions'));
        $this->assertArrayNotHasKey('password', $res->json('data.user'));

        $this->app['auth']->forgetGuards();
        $this->withToken($res->json('data.token'))->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'president@insania.test');
    }

    public function test_a_wrong_password_and_an_unknown_email_look_exactly_the_same(): void
    {
        $this->world();

        $wrong = $this->login('president@insania.test', 'not-the-password');
        $unknown = $this->login('nobody@insania.test', 'not-the-password');

        $wrong->assertStatus(401);
        $unknown->assertStatus(401);
        $this->assertSame($wrong->json(), $unknown->json());
        $this->assertSame('INVALID_CREDENTIALS', $wrong->json('code'));
    }

    public function test_a_switched_off_account_cannot_sign_in(): void
    {
        $this->world();
        User::where('email', 'president@insania.test')->update(['active' => false]);

        $this->login()->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_repeated_failures_lock_the_account_for_a_minute_even_with_the_right_password(): void
    {
        $this->world();

        for ($i = 0; $i < 5; $i++) {
            $this->login('president@insania.test', 'wrong')->assertStatus(401);
        }

        $this->login()->assertStatus(429)->assertJsonPath('code', 'THROTTLED')->assertHeader('Retry-After');
    }

    public function test_logout_ends_that_token(): void
    {
        $this->world();
        $token = $this->login()->json('data.token');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    public function test_tokens_expire(): void
    {
        $this->world();
        config(['sanctum.expiration' => 60]);
        $token = User::where('email', 'president@insania.test')->first()->createToken('old')->plainTextToken;
        DB::table('personal_access_tokens')->update(['created_at' => now()->subHours(2)]);

        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    public function test_protected_routes_need_a_token(): void
    {
        $this->world();

        foreach (['/api/me', '/api/dashboard', '/api/children', '/api/payments', '/api/users', '/api/system/health'] as $url) {
            $this->getJson($url)->assertStatus(401)->assertJsonPath('success', false);
        }
    }

    public function test_preferences_are_saved_per_person(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');

        $this->patchJson('/api/me/preferences', ['show_amounts' => false])->assertOk()->assertJsonPath('data.show_amounts', false);
        $this->getJson('/api/me/preferences')->assertOk()->assertJsonPath('data.show_amounts', false);
        $this->patchJson('/api/me/preferences', ['show_amounts' => 'maybe'])->assertStatus(422);
    }

    public function test_every_response_carries_the_security_headers_and_errors_are_json(): void
    {
        $this->world();

        $res = $this->getJson('/api/does-not-exist');
        $res->assertStatus(404)->assertJsonPath('success', false);
        $res->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy');
    }
}
