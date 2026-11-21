<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
        Storage::fake('local');
    }

    private function as(string $role): User
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsRole($role);
    }

    private function png(int $w = 600, int $h = 400): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 60, 90));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('me.png', (string) ob_get_clean());
    }

    public function test_every_account_has_a_username_and_can_sign_in_with_it_or_with_the_email(): void
    {
        $this->assertSame(['aytam', 'finance', 'maintenance', 'president', 'projects', 'security', 'system'], User::whereIn('email', array_values(array_filter($this->accounts, fn ($e) => $e !== 'family@insania.test')))->orderBy('username')->pluck('username')->all());
        foreach (['aytam', 'AYTAM', '  Aytam ', 'aytam@insania.test'] as $login) {
            $this->postJson('/api/login', ['login' => $login, 'password' => 'DemoPass#2026'])->assertOk()->assertJsonPath('data.user.username', 'aytam');
        }
        $this->postJson('/api/login', ['email' => 'aytam@insania.test', 'password' => 'DemoPass#2026'])->assertOk();   // an older copy of the app still works
        $bad = $this->postJson('/api/login', ['login' => 'aytam', 'password' => 'wrong'])->assertStatus(401);
        $this->postJson('/api/login', ['login' => 'nobody', 'password' => 'wrong'])->assertStatus(401)->assertJsonPath('message', $bad->json('message'));   // no hint which part was wrong
        $this->postJson('/api/login', ['password' => 'x'])->assertStatus(422);
        $this->postJson('/api/login', ['login' => "aytam\nBcc: x@y.z", 'password' => 'x'])->assertStatus(422);
        User::where('username', 'aytam')->update(['active' => false]);
        $this->postJson('/api/login', ['login' => 'aytam', 'password' => 'DemoPass#2026'])->assertStatus(401);       // a switched-off account cannot sign in either way
    }

    public function test_an_account_made_by_the_president_gets_a_free_username_or_the_one_chosen(): void
    {
        $this->as('president_admin');
        $body = fn (array $x) => $x + ['name' => 'New Person', 'role' => 'staff', 'department_key' => 'hr', 'password' => 'Str0ng#Passw0rd'];
        $this->postJson('/api/users', $body(['email' => 'amal@example.org']))->assertCreated()->assertJsonPath('data.username', 'amal');
        $this->postJson('/api/users', $body(['email' => 'amal@other.org']))->assertCreated()->assertJsonPath('data.username', 'amal2');        // taken: a number is added
        $this->postJson('/api/users', $body(['email' => 'c@example.org', 'username' => 'Cashier.One']))->assertCreated()->assertJsonPath('data.username', 'cashier.one');
        $this->postJson('/api/users', $body(['email' => 'd@example.org', 'username' => 'cashier.one']))->assertStatus(422)->assertJsonValidationErrors('username');
        foreach (['ab', 'has space', '-start', 'a@b.c', str_repeat('x', 31)] as $badName) {
            $this->postJson('/api/users', $body(['email' => 'e'.strlen($badName).'@example.org', 'username' => $badName]))->assertStatus(422)->assertJsonValidationErrors('username');
        }
        $this->postJson('/api/login', ['login' => 'cashier.one', 'password' => 'Str0ng#Passw0rd'])->assertOk();

        $id = User::where('username', 'amal')->value('id');
        $this->patchJson("/api/users/$id", ['username' => 'amal.office'])->assertOk()->assertJsonPath('data.username', 'amal.office');
        $this->patchJson("/api/users/$id", ['username' => ''])->assertOk()->assertJsonPath('data.username', 'amal.office');                  // empty means leave it
        $this->patchJson("/api/users/$id", ['username' => 'aytam'])->assertStatus(422);
    }

    public function test_people_change_their_own_username(): void
    {
        $this->as('staff:finance');
        $this->patchJson('/api/me/profile', ['username' => 'Fin.Desk'])->assertOk()->assertJsonPath('data.username', 'fin.desk');
        $this->patchJson('/api/me/profile', ['username' => 'aytam'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->patchJson('/api/me/profile', ['username' => 'a b'])->assertStatus(422);
        $this->postJson('/api/login', ['login' => 'fin.desk', 'password' => 'DemoPass#2026'])->assertOk();
        $this->postJson('/api/login', ['login' => 'finance', 'password' => 'DemoPass#2026'])->assertStatus(401);                              // the old name is free again
    }

    public function test_a_profile_picture_is_private_shared_with_the_foundation_and_replaceable(): void
    {
        $finance = $this->as('staff:finance');
        $this->postJson('/api/me/avatar', ['avatar' => UploadedFile::fake()->createWithContent('x.txt', 'not a picture')])->assertStatus(422);
        $this->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->createWithContent('x.php.png', '<?php echo 1;')], ['Accept' => 'application/json'])->assertStatus(422);
        $r = $this->post('/api/me/avatar', ['avatar' => $this->png()], ['Accept' => 'application/json'])->assertOk();
        $v = $r->json('data.avatar');
        $this->assertNotNull($v);
        $this->assertStringNotContainsString('avatar_path', $r->getContent());                                                          // the file location never leaves the server
        $first = $finance->fresh()->avatar_path;
        Storage::disk('local')->assertExists($first);
        $this->assertStringStartsWith('avatars/', $first);
        $this->assertNotNull(getimagesizefromstring(Storage::disk('local')->get($first)));
        $this->assertLessThanOrEqual(360, getimagesizefromstring(Storage::disk('local')->get($first))[0]);                              // shrunk

        // a colleague sees it (chat, accounts); so does the owner; other foundations and visitors do not
        $this->as('staff:aytam');
        $this->get("/api/avatars/{$finance->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $people = collect($this->getJson('/api/chat/people')->json('data'))->firstWhere('id', $finance->id);
        $this->assertSame($v, $people['avatar']);
        $this->assertSame('finance', $people['username']);
        $g = $this->getJson('/api/chat/conversations')->json('data.0.id');
        $this->as('staff:finance');
        $this->postJson("/api/chat/conversations/$g/messages", ['body' => 'with a picture'])->assertCreated()->assertJsonPath('data.av', $v);
        $this->as('staff:aytam');
        $this->assertSame($v, $this->getJson("/api/chat/conversations/$g/messages")->json('data.0.av'));
        $this->get("/api/avatars/{$this->actingAsRole('staff:aytam')->id}")->assertNotFound();                                          // no picture yet

        $other = $this->otherFoundation();
        $this->app['auth']->forgetGuards();
        \Laravel\Sanctum\Sanctum::actingAs($other['president'], ['*']);
        $this->get("/api/avatars/{$finance->id}")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson("/api/avatars/{$finance->id}")->assertStatus(401);

        // replacing removes the old file; removing clears it
        $this->as('staff:finance');
        $second = $this->post('/api/me/avatar', ['avatar' => $this->png(300, 300)], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($first);
        $this->assertNotSame($first, $finance->fresh()->avatar_path);
        $this->deleteJson('/api/me/avatar')->assertOk()->assertJsonPath('data.avatar', null);
        $this->assertNull($finance->fresh()->avatar_path);
        $this->get("/api/avatars/{$finance->id}")->assertNotFound();
    }

    public function test_every_role_can_set_a_picture_including_the_system_and_family_accounts(): void
    {
        foreach (['system_admin', 'security_admin', 'admin_maintenance', 'president_admin', 'staff:projects', 'user'] as $role) {
            $this->as($role);
            $this->post('/api/me/avatar', ['avatar' => $this->png(100, 100)], ['Accept' => 'application/json'])->assertOk();
            $this->patchJson('/api/me/profile', ['username' => 'u'.substr(md5($role), 0, 6)])->assertOk();
        }
    }
}
