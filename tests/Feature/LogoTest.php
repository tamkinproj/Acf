<?php

namespace Tests\Feature;

use App\Models\Foundation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class LogoTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->bootFoundation();
    }

    public function test_valid_logo_is_reencoded_stored_under_a_random_name_and_served_publicly(): void
    {
        $r = $this->asDevice($this->admin)->post('/api/foundation/logo', ['logo' => UploadedFile::fake()->image('../../evil name.jpg', 1200, 800)], ['Accept' => 'application/json'])->assertOk();

        $f = Foundation::current();
        $this->assertMatchesRegularExpression('#^foundation/logo-[a-z0-9]{24}\.png$#', $f->logo_path);
        $this->assertSame($f->logo_hash, $r->json('data.logo_hash'));
        Storage::disk('local')->assertExists($f->logo_path);

        $bytes = Storage::disk('local')->get($f->logo_path);
        $this->assertStringStartsWith("\x89PNG", $bytes);
        [$w, $h] = getimagesizefromstring($bytes);
        $this->assertLessThanOrEqual(512, max($w, $h), 'oversized logos are scaled down');

        $this->get('/assets/logo')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($f->logo_hash, SyncChangeHash::latestFor($f));
    }

    public function test_non_images_and_disguised_scripts_are_refused(): void
    {
        $this->asDevice($this->admin);
        foreach ([
            UploadedFile::fake()->createWithContent('logo.png', '<?php echo 1; ?>'),
            UploadedFile::fake()->createWithContent('logo.jpg', "GIF89a<?php system('id'); ?>"),
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            // Valid PNG header, truncated body: passes the header sniff but cannot be decoded.
            UploadedFile::fake()->createWithContent('logo.png', substr(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), 0, 40)),
        ] as $file) {
            $this->post('/api/foundation/logo', ['logo' => $file], ['Accept' => 'application/json'])->assertStatus(422);
        }
        $this->assertNull(Foundation::current()->logo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('foundation'));
    }

    public function test_only_managers_can_change_the_logo_and_replacing_deletes_the_old_file(): void
    {
        $this->asDevice($this->makeUser('staff'))->post('/api/foundation/logo', ['logo' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertStatus(403);

        $this->asDevice($this->admin);
        $this->post('/api/foundation/logo', ['logo' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertOk();
        $first = Foundation::current()->logo_path;
        $this->post('/api/foundation/logo', ['logo' => UploadedFile::fake()->image('b.png', 50, 50)], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('local')->assertMissing($first);
        $this->assertCount(1, Storage::disk('local')->allFiles('foundation'));

        $this->deleteJson('/api/foundation/logo')->assertOk();
        $this->assertNull(Foundation::current()->logo_path);
        $this->get('/assets/logo')->assertStatus(404);
    }

    public function test_logo_change_replicates_the_hash_but_never_the_path(): void
    {
        $this->asDevice($this->admin)->post('/api/foundation/logo', ['logo' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertOk();
        $last = \App\Models\SyncChange::where('entity', 'foundations')->orderByDesc('seq')->first();
        $this->assertArrayHasKey('logo_hash', $last->payload);
        $this->assertStringNotContainsString('foundation/logo-', json_encode($last->payload));
    }
}

final class SyncChangeHash
{
    public static function latestFor(Foundation $f): ?string
    {
        return \App\Models\SyncChange::where('entity', 'foundations')->where('entity_id', $f->id)->orderByDesc('seq')->first()->payload['logo_hash'] ?? null;
    }
}
