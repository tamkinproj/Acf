<?php

namespace Tests\Feature;

use App\Updates\UpdateException;
use App\Updates\UpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;
use ZipArchive;

class UpdatesTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private string $site;

    private $priv;

    private string $pub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = sys_get_temp_dir().'/insania-upd-'.bin2hex(random_bytes(5));
        foreach (['insania_app/app', 'insania_app/config', 'insania_app/storage', 'public_html/app'] as $d) {
            mkdir("$this->site/$d", 0775, true);
        }
        file_put_contents("$this->site/insania_app/BUILD", 'B1');
        file_put_contents("$this->site/insania_app/app/a.php", "<?php\nreturn 'old';\n");
        file_put_contents("$this->site/insania_app/.env", 'SECRET=1');
        file_put_contents("$this->site/public_html/app/x.js", 'old js');
        file_put_contents("$this->site/public_html/app/gone.js", 'to delete');

        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $this->priv);
        $this->pub = openssl_pkey_get_details($key)['key'];

        config(['updates.public_key' => $this->pub, 'updates.dir' => "$this->site/insania_app/storage/app/updates",
            'updates.app_path' => "$this->site/insania_app", 'updates.public_path' => "$this->site/public_html"]);
    }

    /** @return array{0: string, 1: string} zip path, signature text */
    private function makePatch(array $files, array $manifest = [], ?string $privOverride = null, bool $tamper = false): array
    {
        $manifest += ['name' => 'Test patch', 'version' => '1.1', 'build' => 'B2', 'from_build' => 'B1', 'notes' => 'test'];
        $path = "$this->site/p".bin2hex(random_bytes(3)).'.zip';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE);
        $z->addFromString('manifest.json', json_encode($manifest));
        foreach ($files as $n => $c) {
            $z->addFromString($n, $c);
        }
        $z->close();
        $bytes = file_get_contents($path);
        openssl_sign($bytes, $sig, $privOverride ?? $this->priv, OPENSSL_ALGO_SHA256);
        if ($tamper) {
            file_put_contents($path, $bytes.'x');
        }

        return [$path, base64_encode($sig)];
    }

    private function stage(array $p): string
    {
        return app(UpdateService::class)->store($p[0], $p[1]);
    }

    private function good(): array
    {
        return $this->makePatch([
            'insania_app/app/a.php' => "<?php\nreturn 'new';\n", 'insania_app/app/b.php' => "<?php\nreturn 'added';\n",
            'public_html/app/x.js' => 'new js', 'insania_app/BUILD' => 'B2',
        ], ['delete' => ['public_html/app/gone.js']]);
    }

    private function refused(array $p, string $contains): void
    {
        try {
            $svc = app(UpdateService::class);
            $svc->inspect($svc->store($p[0], $p[1]));
            $this->fail('The patch should have been refused.');
        } catch (UpdateException $e) {
            $this->assertStringContainsString($contains, $e->getMessage());
        }
    }

    public function test_a_signed_patch_applies_and_can_be_undone(): void
    {
        $svc = app(UpdateService::class);
        $r = $svc->inspect($this->stage($this->good()))['summary'];
        $this->assertSame(['add' => 1, 'change' => 3, 'delete' => 1], ['add' => $r['add'], 'change' => $r['change'], 'delete' => $r['delete']]);   // b.php added; a.php, x.js, BUILD changed; gone.js deleted

        $entry = $svc->apply($this->stage($this->good()), 7);
        $this->assertSame('B2', trim(file_get_contents("$this->site/insania_app/BUILD")));
        $this->assertStringContainsString('new', file_get_contents("$this->site/insania_app/app/a.php"));
        $this->assertFileExists("$this->site/insania_app/app/b.php");
        $this->assertFileDoesNotExist("$this->site/public_html/app/gone.js");
        $this->assertSame('new js', file_get_contents("$this->site/public_html/app/x.js"));
        $this->assertSame('SECRET=1', file_get_contents("$this->site/insania_app/.env"));
        $this->assertSame('applied', $svc->latestApplied()['status']);
        $this->assertDirectoryExists("$this->site/insania_app/storage/app/updates/backups/{$entry['id']}");

        $svc->undo($entry['id'], 7);
        $this->assertSame('B1', trim(file_get_contents("$this->site/insania_app/BUILD")));
        $this->assertStringContainsString('old', file_get_contents("$this->site/insania_app/app/a.php"));
        $this->assertFileDoesNotExist("$this->site/insania_app/app/b.php");
        $this->assertSame('to delete', file_get_contents("$this->site/public_html/app/gone.js"));
        $this->assertSame('old js', file_get_contents("$this->site/public_html/app/x.js"));
        $this->assertNull($svc->latestApplied());
    }

    public function test_only_the_latest_update_can_be_undone_and_only_once(): void
    {
        $svc = app(UpdateService::class);
        $e1 = $svc->apply($this->stage($this->good()));
        $e2 = $svc->apply($this->stage($this->makePatch(['insania_app/app/c.php' => "<?php\nreturn 1;\n", 'insania_app/BUILD' => 'B3'], ['build' => 'B3', 'from_build' => 'B2'])));
        $this->expectException(UpdateException::class);
        try {
            $svc->undo($e1['id']);
        } finally {
            $svc->undo($e2['id']);
            $this->assertSame('B2', trim(file_get_contents("$this->site/insania_app/BUILD")));
            $svc->undo($e1['id']);
            $this->assertSame('B1', trim(file_get_contents("$this->site/insania_app/BUILD")));
        }
    }

    public function test_a_patch_without_the_owners_signature_is_refused_and_nothing_changes(): void
    {
        $other = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($other, $otherPriv);
        $this->refused($this->makePatch(['insania_app/app/a.php' => "<?php\nreturn 'evil';\n"], [], $otherPriv), 'signature does not match');
        $this->refused($this->makePatch(['insania_app/app/a.php' => "<?php\nreturn 'evil';\n"], [], null, true), 'signature does not match');
        $this->refused([$this->good()[0], 'not-a-signature'], 'signature');
        $this->assertStringContainsString('old', file_get_contents("$this->site/insania_app/app/a.php"));
    }

    public function test_unsafe_or_forbidden_paths_are_refused(): void
    {
        foreach ([
            'insania_app/../evil.php' => 'unsafe path', 'insania_app/.env' => 'may not change', 'insania_app/storage/x.txt' => 'may not change',
            'insania_app/vendor/laravel/x.php' => 'may not change', 'insania_app/public/x.php' => 'may not change', 'outside/x.php' => 'outside the allowed',
            '/abs/x.php' => 'unsafe path', 'public_html/server/evil.php' => 'may not change', 'insania_app/app/../../x.php' => 'unsafe path',
        ] as $name => $msg) {
            $this->refused($this->makePatch([$name => "<?php\n"]), $msg);
        }
        $this->assertFileDoesNotExist("$this->site/evil.php");
    }

    public function test_links_inside_the_zip_are_refused(): void
    {
        $path = "$this->site/link.zip";
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE);
        $z->addFromString('manifest.json', json_encode(['name' => 'x', 'version' => '1', 'build' => 'B9']));
        $z->addFromString('insania_app/app/link', '/etc/passwd');
        $z->setExternalAttributesName('insania_app/app/link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $z->close();
        openssl_sign(file_get_contents($path), $sig, $this->priv, OPENSSL_ALGO_SHA256);
        $this->refused([$path, base64_encode($sig)], 'link');
    }

    public function test_php_with_a_syntax_error_is_refused_before_anything_is_written(): void
    {
        $this->refused($this->makePatch(['insania_app/app/a.php' => "<?php\nthis is not php {{\n", 'insania_app/app/b.php' => "<?php\n"]), 'PHP error');
        $this->assertFileDoesNotExist("$this->site/insania_app/app/b.php");
        $this->assertStringContainsString('old', file_get_contents("$this->site/insania_app/app/a.php"));
    }

    public function test_the_patch_must_fit_the_build_on_the_server(): void
    {
        $this->refused($this->makePatch(['insania_app/app/c.php' => "<?php\n"], ['from_build' => 'SOMETHING-ELSE']), 'is for build SOMETHING-ELSE');
        $this->refused($this->makePatch(['insania_app/app/c.php' => "<?php\n"], ['build' => 'B1', 'from_build' => null]), 'already runs build B1');
    }

    public function test_a_failure_while_writing_puts_everything_back(): void
    {
        mkdir("$this->site/insania_app/app/blocked");                  // a folder where the patch wants to write a file
        $svc = app(UpdateService::class);
        $id = $this->stage($this->makePatch([
            'insania_app/app/a.php' => "<?php\nreturn 'new';\n", 'public_html/app/x.js' => 'new js', 'insania_app/app/blocked' => "<?php\n", 'insania_app/BUILD' => 'B2',
        ], ['delete' => ['public_html/app/gone.js']]));
        try {
            $svc->apply($id);
            $this->fail('should have failed');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('undone automatically', $e->getMessage());
        }
        $this->assertStringContainsString('old', file_get_contents("$this->site/insania_app/app/a.php"));
        $this->assertSame('old js', file_get_contents("$this->site/public_html/app/x.js"));
        $this->assertSame('to delete', file_get_contents("$this->site/public_html/app/gone.js"));
        $this->assertSame('B1', trim(file_get_contents("$this->site/insania_app/BUILD")));
        $this->assertSame('failed', collect($svc->history())->last()['status']);
        $this->assertSame([], glob("$this->site/insania_app/storage/app/updates/backups/*") ?: []);
        $this->assertNull($svc->latestApplied());
    }

    public function test_only_the_system_admin_reaches_the_updates_api_and_it_works_end_to_end(): void
    {
        $this->world();
        foreach (['president_admin', 'security_admin', 'admin_maintenance', 'staff:aytam', 'user'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson('/api/system/updates')->assertForbidden();
            $this->postJson('/api/system/updates/check')->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->actingAsRole('system_admin');
        $this->getJson('/api/system/updates')->assertOk()->assertJsonPath('data.build', 'B1');

        [$zip, $sig] = $this->good();
        $up = fn ($z, $s) => $this->post('/api/system/updates/check', ['patch' => UploadedFile::fake()->createWithContent('p.zip', file_get_contents($z)), 'signature' => UploadedFile::fake()->createWithContent('p.sig', $s)], ['Accept' => 'application/json']);

        $up($zip, 'bogus')->assertStatus(422)->assertJsonPath('code', 'UPDATE_REJECTED');
        $check = $up($zip, $sig)->assertOk()->assertJsonPath('data.build', 'B2')->assertJsonPath('data.delete', 1)->json('data');

        $this->postJson("/api/system/updates/{$check['id']}/apply")->assertOk()->assertJsonPath('data.status', 'applied');
        $this->assertSame('B2', trim(file_get_contents("$this->site/insania_app/BUILD")));
        $this->getJson('/api/system/updates')->assertJsonPath('data.build', 'B2')->assertJsonPath('data.history.0.build', 'B2');
        $undo = $this->getJson('/api/system/updates')->json('data.undo_id');
        $this->postJson("/api/system/updates/$undo/undo")->assertOk();
        $this->assertSame('B1', trim(file_get_contents("$this->site/insania_app/BUILD")));
        $this->postJson("/api/system/updates/$undo/undo")->assertStatus(422)->assertJsonPath('code', 'UNDO_FAILED');
        $this->postJson('/api/system/updates/'.str_repeat('a', 24).'/apply')->assertStatus(422);
    }

    public function test_the_web_fallback_checks_applies_and_undoes_with_the_setup_code(): void
    {
        $token = \App\Install\InstallState::token();
        [$zip, $sig] = $this->good();
        $files = fn () => ['patch' => UploadedFile::fake()->createWithContent('p.zip', file_get_contents($zip)), 'signature' => UploadedFile::fake()->createWithContent('p.sig', $sig)];

        $this->post('/upgrade/patch', ['token' => 'wrong'] + $files())->assertStatus(403);
        $this->post('/upgrade/patch', ['token' => $token, 'signature' => UploadedFile::fake()->createWithContent('p.sig', 'bogus'), 'patch' => UploadedFile::fake()->createWithContent('p.zip', file_get_contents($zip))])
            ->assertStatus(422)->assertSee('signature');

        $page = $this->post('/upgrade/patch', ['token' => $token] + $files())->assertOk()->assertSee('Apply update');
        preg_match('/name="id" value="([a-f0-9]{24})"/', $page->getContent(), $m);
        $this->post('/upgrade/apply', ['token' => 'wrong', 'id' => $m[1]])->assertStatus(403);
        $this->post('/upgrade/apply', ['token' => $token, 'id' => $m[1]])->assertOk()->assertSee('Updated to');
        $this->assertSame('B2', trim(file_get_contents("$this->site/insania_app/BUILD")));

        $latest = app(UpdateService::class)->latestApplied();
        $this->get('/upgrade')->assertOk()->assertSee('Undo last update');
        $this->post('/upgrade/undo', ['token' => $token, 'id' => $latest['id']])->assertOk()->assertSee('Undone');
        $this->assertSame('B1', trim(file_get_contents("$this->site/insania_app/BUILD")));
    }

    private function bundle(array $patch, ?string $sigOverride = null, array $extra = []): string
    {
        $path = "$this->site/bundle".bin2hex(random_bytes(3)).'.zip';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE);
        $z->addFromString('patch.zip', file_get_contents($patch[0]));
        $z->addFromString('signature.sig', $sigOverride ?? $patch[1]);
        foreach ($extra as $n => $c) {
            $z->addFromString($n, $c);
        }
        $z->close();

        return $path;
    }

    private function auto(string $zip, ?string $sig = null)
    {
        $files = ['patch' => UploadedFile::fake()->createWithContent('p.zip', file_get_contents($zip))] + ($sig === null ? [] : ['signature' => UploadedFile::fake()->createWithContent('p.sig', $sig)]);

        return $this->post('/api/system/updates/auto', $files, ['Accept' => 'application/json']);
    }

    public function test_one_upload_updates_the_system_straight_away(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');

        // a one-file bundle
        $this->auto($this->bundle($this->good()))->assertOk()->assertJsonPath('data.status', 'applied')->assertJsonPath('data.build', 'B2');
        $this->assertSame('B2', trim(file_get_contents("$this->site/insania_app/BUILD")));
        $this->assertFileExists("$this->site/insania_app/app/b.php");
        $undo = $this->getJson('/api/system/updates')->json('data.undo_id');
        $this->postJson("/api/system/updates/$undo/undo")->assertOk();
        $this->assertSame('B1', trim(file_get_contents("$this->site/insania_app/BUILD")));

        // a plain patch zip with its signature file works in one step too
        [$zip, $sig] = $this->good();
        $this->auto($zip, $sig)->assertOk()->assertJsonPath('data.build', 'B2');
    }

    public function test_one_upload_refuses_what_the_two_step_way_refuses_and_changes_nothing(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');
        $other = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($other, $otherPriv);

        $this->auto($this->bundle($this->makeBundleInput($otherPriv)))->assertStatus(422)->assertJsonPath('code', 'UPDATE_REJECTED')->assertJsonFragment(['message' => 'The signature does not match this patch. It was not signed with the owner\'s key, or the file was changed. Nothing was done.']);
        $this->auto($this->bundle($this->good(), null, ['extra.txt' => 'x']))->assertStatus(422);                    // a bundle holds exactly two files
        [$zip] = $this->good();
        $this->auto($zip)->assertStatus(422)->assertSee('no signature');                                             // plain zip with no signature
        $this->auto($this->bundle($this->makeBundleInput(null, true)))->assertStatus(422);                         // tampered after signing
        $this->assertSame('B1', trim(file_get_contents("$this->site/insania_app/BUILD")));
        $this->assertFileDoesNotExist("$this->site/insania_app/app/b.php");

        foreach (['president_admin', 'security_admin', 'staff:aytam'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->auto($this->bundle($this->good()))->assertForbidden();
        }
        $this->assertSame('B1', trim(file_get_contents("$this->site/insania_app/BUILD")));
    }

    private function makeBundleInput(?string $priv = null, bool $tamper = false): array
    {
        return $this->makePatch(['insania_app/app/b.php' => "<?php\nreturn 1;\n", 'insania_app/BUILD' => 'B2'], [], $priv, $tamper);
    }
}
