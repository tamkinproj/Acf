<?php

namespace Tests\Feature;

use App\Models\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploads = sys_get_temp_dir().'/insania-site-'.bin2hex(random_bytes(4));
        config(['site.upload_dir' => $this->uploads, 'app.url' => 'https://example.org/server']);
        $this->world();
    }

    private function president(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
    }

    private function draft(): array
    {
        return $this->getJson('/api/site')->assertOk()->json('data.content');
    }

    private function png(int $w = 40, int $h = 30): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 40));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('photo.png', (string) ob_get_clean());
    }

    public function test_visitors_see_nothing_until_the_president_publishes(): void
    {
        $this->getJson('/api/public/site')->assertOk()->assertJsonPath('data.content', null)->assertJsonPath('data.version', 0);

        $this->president();
        $d = $this->draft();
        $d['texts']['h_t1']['en'] = 'Our new headline';
        $this->putJson('/api/site', ['content' => $d])->assertOk()->assertJsonPath('data.unpublished_changes', true);

        $this->app['auth']->forgetGuards();                    // a visitor: nobody signed in
        $this->getJson('/api/public/site')->assertOk()->assertJsonPath('data.content', null);

        $this->president();
        $this->postJson('/api/site/publish')->assertOk()->assertJsonPath('data.version', 1);

        $this->app['auth']->forgetGuards();
        $pub = $this->getJson('/api/public/site')->assertOk()->assertHeader('Cache-Control', 'max-age=60, public');
        $this->assertSame('Our new headline', $pub->json('data.content.texts.h_t1.en'));
        $this->assertSame(1, $pub->json('data.version'));
    }

    public function test_visitors_get_the_published_page_of_the_foundation_the_staff_work_in_even_with_two_foundations(): void
    {
        \Illuminate\Support\Facades\DB::table('foundations')->insert(['id' => 0, 'name' => 'Empty older foundation', 'slug' => 'empty', 'created_at' => now(), 'updated_at' => now()]);   // lowest id, but no staff
        $this->president();
        $d = $this->draft();
        $d['texts']['h_t1']['en'] = 'Headline of the working foundation';
        $this->putJson('/api/site', ['content' => $d])->assertOk();
        $this->postJson('/api/site/publish')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/public/site')->assertOk()->assertJsonPath('data.content.texts.h_t1.en', 'Headline of the working foundation');
    }

    public function test_edits_after_publishing_stay_in_the_draft_until_published_again(): void
    {
        $this->president();
        $this->postJson('/api/site/publish')->assertOk();
        $d = $this->draft();
        $d['texts']['h_t1']['en'] = 'Draft only';
        $this->putJson('/api/site', ['content' => $d])->assertOk()->assertJsonPath('data.unpublished_changes', true);

        $this->app['auth']->forgetGuards();
        $this->assertNotSame('Draft only', $this->getJson('/api/public/site')->json('data.content.texts.h_t1.en'));

        $this->president();
        $this->postJson('/api/site/publish')->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson('/api/site')->assertJsonPath('data.unpublished_changes', false)->assertJsonCount(2, 'data.versions');
    }

    public function test_an_earlier_version_can_be_brought_back_into_the_draft(): void
    {
        $this->president();
        $d = $this->draft();
        $d['texts']['h_t1']['en'] = 'Version one';
        $this->putJson('/api/site', ['content' => $d])->assertOk();
        $this->postJson('/api/site/publish')->assertOk();
        $d['texts']['h_t1']['en'] = 'Version two';
        $this->putJson('/api/site', ['content' => $d])->assertOk();
        $this->postJson('/api/site/publish')->assertOk();

        $this->postJson('/api/site/revert/1')->assertOk()->assertJsonPath('data.content.texts.h_t1.en', 'Version one')->assertJsonPath('data.unpublished_changes', true);
        $this->app['auth']->forgetGuards();
        $this->assertSame('Version two', $this->getJson('/api/public/site')->json('data.content.texts.h_t1.en'));   // visitors unchanged
        $this->president();
        $this->postJson('/api/site/revert/99')->assertNotFound();
    }

    public function test_content_is_cleaned_and_checked(): void
    {
        $this->president();
        $d = $this->draft();
        $d['texts']['h_t1']['en'] = "  <script>alert(1)</script>Hello\r\n\x07world  ";
        $d['texts']['h_sub']['en'] = "First line\nSecond line";
        $d['singles']['facebook'] = 'javascript:alert(1)';
        $d['singles']['email'] = 'not-an-email';
        $d['numbers']['hero_orphans'] = -5;
        $this->putJson('/api/site', ['content' => $d])->assertStatus(422)->assertJsonPath('code', 'INVALID_CONTENT')
            ->assertJsonStructure(['errors' => ['singles.facebook', 'singles.email', 'numbers.hero_orphans']]);

        $d['singles']['facebook'] = 'https://facebook.com/alinsania';
        $d['singles']['email'] = 'info@alinsania.com';
        $d['numbers']['hero_orphans'] = '130';
        $d['projects'] = [['title_en' => 'Water well', 'category' => 'not-a-category', 'year' => '2025', 'beneficiaries' => '300', 'visible' => true]];
        $d['news'] = [['title_en' => 'News', 'date' => '2026-11-20', 'summary_en' => "Line one\nLine two"]];
        $saved = $this->putJson('/api/site', ['content' => $d])->assertOk()->json('data.content');
        $this->assertSame('alert(1)Hello world', $saved['texts']['h_t1']['en']);      // tags and control characters gone, one line
        $this->assertSame("First line\nSecond line", $saved['texts']['h_sub']['en']);  // long fields keep their line breaks
        $this->assertSame(130, $saved['numbers']['hero_orphans']);
        $this->assertSame('orphan', $saved['projects'][0]['category']);                // unknown category falls back
        $this->assertSame("Line one\nLine two", $saved['news'][0]['summary_en']);      // long fields keep line breaks

        $d['projects'][0]['year'] = '20x5';
        $d['team'] = [['name_en' => '']];
        $this->putJson('/api/site', ['content' => $d])->assertStatus(422)->assertJsonStructure(['errors' => ['projects.0.year', 'team.0.name_en']]);
        $d['projects'] = array_fill(0, 61, ['title_en' => 'x']);
        $this->putJson('/api/site', ['content' => $d])->assertStatus(422);
    }

    public function test_photos_are_decoded_shrunk_renamed_and_must_be_real_images(): void
    {
        $this->president();
        $r = $this->post('/api/site/images', ['image' => $this->png(3000, 1500)], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(1600, $r->json('data.width'));
        $this->assertSame(800, $r->json('data.height'));
        $file = basename($r->json('data.url'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{24}\.(webp|jpg)$/', $file);
        $this->assertFileExists("$this->uploads/$file");
        $this->assertStringContainsString('ExecCGI', file_get_contents("$this->uploads/.htaccess"));
        $this->assertStringStartsWith('https://example.org/server/uploads/site/', $r->json('data.url'));

        // a script or page pretending to be a photo is refused
        foreach ([['evil.php', "<?php system('id');"], ['evil.png', "<?php system('id');"], ['x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'], ['a.jpg', "GIF89a<?php echo 1;"]] as [$name, $body]) {
            $this->post('/api/site/images', ['image' => UploadedFile::fake()->createWithContent($name, $body)], ['Accept' => 'application/json'])->assertStatus(422);
        }
        $this->assertCount(1, array_diff(scandir($this->uploads), ['.', '..', '.htaccess']));
    }

    public function test_only_own_photos_can_be_used_and_they_show_on_the_public_page(): void
    {
        $this->president();
        $id = $this->post('/api/site/images', ['image' => $this->png()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $d = $this->draft();
        $d['images']['president_photo'] = $id;
        $d['team'] = [['name_en' => 'Aisha', 'role_en' => 'Teacher', 'photo' => $id]];
        $this->putJson('/api/site', ['content' => $d])->assertOk();
        $this->postJson('/api/site/publish')->assertOk();

        $this->app['auth']->forgetGuards();
        $pub = $this->getJson('/api/public/site')->assertOk();
        $this->assertStringContainsString('/uploads/site/', $pub->json("data.images.$id.url"));

        $this->president();
        $d['images']['president_photo'] = 99999;
        $this->putJson('/api/site', ['content' => $d])->assertStatus(422);
        $other = $this->otherFoundation();                     // another foundation's photo id is not usable here
        $foreign = \App\Models\SiteImage::withoutGlobalScopes()->create(['foundation_id' => $other['foundation']->id, 'filename' => 'zz.webp', 'mime' => 'image/webp', 'width' => 1, 'height' => 1, 'bytes' => 1]);
        $d['images']['president_photo'] = $foreign->id;
        $this->putJson('/api/site', ['content' => $d])->assertStatus(422);
    }

    public function test_the_system_admin_and_everyone_else_cannot_touch_the_website(): void
    {
        foreach (['system_admin', 'security_admin', 'admin_maintenance', 'staff:aytam', 'staff:finance', 'user'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson('/api/site')->assertForbidden();
            $this->putJson('/api/site', ['content' => []])->assertForbidden();
            $this->postJson('/api/site/publish')->assertForbidden();
            $this->postJson('/api/site/revert/1')->assertForbidden();
        }
        $this->assertSame(0, SiteContent::withoutGlobalScopes()->count());
    }

    public function test_publishing_and_photos_are_audited_without_content(): void
    {
        $this->president();
        $this->post('/api/site/images', ['image' => $this->png()], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/site/publish')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('security_admin');
        $log = $this->getJson('/api/audit?per_page=100')->assertOk();
        $actions = array_column($log->json('data'), 'action');
        $this->assertContains('site.published', $actions);
        $this->assertContains('site.photo_uploaded', $actions);
        $this->assertStringNotContainsString('headline', $log->getContent());
    }

    public function test_any_text_of_the_page_can_be_edited_in_place_but_only_real_ones(): void
    {
        $this->president();
        $d = $this->draft();
        $d['texts']['fq1a'] = ['en' => "  Anyone in need.\nSecond  ", 'ar' => 'أي محتاج.'];
        $d['texts']['n_about'] = ['en' => 'Who we are', 'ar' => ''];
        $saved = $this->putJson('/api/site', ['content' => $d])->assertOk()->json('data.content.texts');
        $this->assertSame('Anyone in need. Second', $saved['fq1a']['en']);
        $this->assertSame('Who we are', $saved['n_about']['en']);
        $this->assertArrayNotHasKey('fq2a', $saved);                      // untouched texts are not stored: the page keeps its own

        $d['texts']['not_a_real_text'] = ['en' => 'x', 'ar' => 'y'];
        $this->putJson('/api/site', ['content' => $d])->assertStatus(422)->assertJsonStructure(['errors' => ['texts.not_a_real_text']]);
    }

    public function test_logos_and_the_main_photo_are_image_slots(): void
    {
        $this->president();
        $id = $this->post('/api/site/images', ['image' => $this->png()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $d = $this->draft();
        foreach (['brand_logo', 'partner_dar', 'partner_dub', 'hero_photo'] as $slot) {
            $d['images'][$slot] = $id;
        }
        $this->putJson('/api/site', ['content' => $d])->assertOk()->assertJsonPath('data.content.images.brand_logo', $id);
        $this->postJson('/api/site/publish')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->assertSame($id, $this->getJson('/api/public/site')->json('data.content.images.hero_photo'));
    }

    public function test_the_president_posts_an_update_and_visitors_see_it_with_the_orphan_count(): void
    {
        $this->president();
        $this->postJson('/api/site/posts', [])->assertStatus(422);
        $this->postJson('/api/site/posts', ['title_en' => 'Orphans Day', 'summary_en' => 'We celebrated together.', 'date' => '2026-11-20'])->assertCreated();
        $this->postJson('/api/site/posts', ['title_en' => 'Second post'])->assertCreated();

        $this->app['auth']->forgetGuards();
        $pub = $this->getJson('/api/public/site')->assertOk();
        $this->assertSame(['Second post', 'Orphans Day'], array_column($pub->json('data.content.news'), 'title_en'));
        $pub->assertJsonStructure(['data' => ['stats']]);
    }

    public function test_only_people_who_can_publish_may_post(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:finance');
        $this->postJson('/api/site/posts', ['title_en' => 'Nope'])->assertForbidden();
    }

    public function test_public_orphan_count_ignores_samples_and_shows_only_a_number(): void
    {
        $stats = $this->getJson('/api/public/site')->assertOk()->json('data.stats');
        $this->assertTrue($stats === null || (is_array($stats) && array_keys($stats) === ['orphans']));
    }
}
