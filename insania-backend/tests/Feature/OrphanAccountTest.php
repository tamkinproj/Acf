<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\User;
use App\Support\ArabicNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class OrphanAccountTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_staff_make_accounts_for_orphans_who_sign_in_must_choose_a_password_and_see_only_themselves(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $n = Child::count();
        $this->assertGreaterThan(1, $n);

        $this->postJson('/api/children/accounts', [])->assertStatus(422);                                          // needs their own password
        $this->postJson('/api/children/accounts', ['confirm_password' => 'wrong-one-123'])->assertStatus(422);
        $r = $this->postJson('/api/children/accounts', ['confirm_password' => 'DemoPass#2026'])->assertOk();
        $this->assertSame($n, $r->json('data.created'));
        $this->assertSame(0, $r->json('data.remaining'));
        $cred = $r->json('data.credentials.0');
        $this->assertMatchesRegularExpression('/^[A-Za-z2-9]{4}-[A-Za-z2-9]{4}-[A-Za-z2-9]{4}$/', $cred['password']);
        $u = User::where('username', $cred['username'])->firstOrFail();
        $this->assertSame('user', $u->role);
        $this->assertTrue($u->must_change_password);
        $this->assertNotSame($cred['password'], $u->password, 'only a hash is stored');

        $again = $this->postJson('/api/children/accounts', ['confirm_password' => 'DemoPass#2026'])->assertOk();
        $this->assertSame(0, $again->json('data.created'), 'nobody gets a second account');
        $this->assertSame($n, User::whereNotNull('child_id')->count());

        // the orphan signs in
        $this->app['auth']->forgetGuards();
        $login = $this->postJson('/api/login', ['email' => $cred['username'], 'password' => $cred['password']])->assertOk();
        $token = $login->json('data.token');
        $this->assertTrue($login->json('data.user.must_change_password'));
        $h = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/my/family', $h)->assertStatus(403)->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/me', $h)->assertOk();

        $new = 'Rivers-Bright-Tulip-52';
        $this->postJson('/api/me/password', ['current_password' => $cred['password'], 'password' => $new], $h)->assertOk();
        $this->assertFalse((bool) $u->fresh()->must_change_password);

        $this->app['auth']->forgetGuards();
        $t2 = $this->postJson('/api/login', ['email' => $cred['username'], 'password' => $new])->assertOk()->json('data.token');
        $mine = $this->getJson('/api/my/family', ['Authorization' => 'Bearer '.$t2])->assertOk();
        $this->assertCount(1, $mine->json('data'));
        $this->assertSame($cred['child_id'], $mine->json('data.0.id'));
        $this->assertTrue($mine->json('data.0.is_self'));
        $this->getJson('/api/children', ['Authorization' => 'Bearer '.$t2])->assertForbidden();              // no office features
        $this->getJson('/api/payments', ['Authorization' => 'Bearer '.$t2])->assertForbidden();
    }

    public function test_only_people_with_the_permission_can_make_accounts_and_a_reset_gives_a_new_temporary_password(): void
    {
        $this->world();
        $this->actingAsRole('staff:finance');
        $this->postJson('/api/children/accounts', ['confirm_password' => 'DemoPass#2026'])->assertForbidden();

        $this->actingAsRole('staff:aytam');
        $child = Child::first();
        $a = $this->postJson("/api/children/{$child->id}/account", ['confirm_password' => 'DemoPass#2026'])->assertCreated();
        $b = $this->postJson("/api/children/{$child->id}/account", ['confirm_password' => 'DemoPass#2026'])->assertOk();
        $this->assertSame($a->json('data.username'), $b->json('data.username'));
        $this->assertNotSame($a->json('data.password'), $b->json('data.password'));
        $this->assertSame(1, User::where('child_id', $child->id)->count());
    }

    public function test_arabic_names_are_written_in_latin_letters(): void
    {
        $this->assertSame('Abdulrahman Muhammad', ArabicNames::toEnglish('عبد الرحمن محمد'));
        $this->assertSame('Layla Hassan', ArabicNames::toEnglish('ليلى حسن'));
        $this->assertSame('Already English', ArabicNames::toEnglish('Already English'));
        $this->assertSame('', ArabicNames::toEnglish(''));
        $en = ArabicNames::toEnglish('نوردينه نورسين هارون أمسائن');
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $en);
        $this->assertStringContainsString('Harun', $en);
    }
}
