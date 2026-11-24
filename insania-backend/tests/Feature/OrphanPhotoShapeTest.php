<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Support\PrivateImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** An orphan's photo must be upright 3:4, the shape of the box in the Dar Al Ber report; older photos are cropped for the report. */
class OrphanPhotoShapeTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function jpeg(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 40, 120, 200));
        imagefilledrectangle($im, 0, 0, $w, (int) ($h / 4), imagecolorallocate($im, 220, 60, 60));   // the top, where faces are
        ob_start();
        imagejpeg($im);

        return (string) ob_get_clean();
    }

    private function up(string $bytes)
    {
        return $this->post('/api/children/'.$this->kid.'/photos', ['photo' => UploadedFile::fake()->createWithContent('p.jpg', $bytes)], ['Accept' => 'application/json']);
    }

    private int $kid;

    public function test_only_upright_three_by_four_photos_are_accepted(): void
    {
        Storage::fake('local');
        $this->world();
        $this->actingAsRole('staff:aytam');
        $this->kid = (int) Child::withoutGlobalScopes()->where('status', 'active')->orderBy('id')->value('id');
        $this->up($this->jpeg(600, 800))->assertCreated();
        $this->up($this->jpeg(1200, 1600))->assertCreated();
        $this->up($this->jpeg(603, 800))->assertCreated();   // within 3%
        foreach ([[800, 800], [1600, 1200], [800, 1200], [600, 900]] as [$w, $h]) {
            $r = $this->up($this->jpeg($w, $h))->assertStatus(422);
            $this->assertStringContainsString('3 wide by 4 tall', (string) $r->json('errors.photo.0'));
        }
        $stored = Storage::disk('local')->get(\App\Models\ChildPhoto::withoutGlobalScopes()->where('child_id', $this->kid)->orderBy('id')->value('path'));
        [$w, $h] = getimagesizefromstring($stored);
        $this->assertEqualsWithDelta(0.75, $w / $h, 0.01);
    }

    public function test_the_report_crops_an_older_photo_to_three_by_four_keeping_the_top(): void
    {
        foreach ([[1600, 1200], [1000, 1000], [600, 1200]] as [$w, $h]) {
            $out = PrivateImages::cropToRatio($this->jpeg($w, $h), 3 / 4);
            [$ow, $oh] = getimagesizefromstring($out);
            $this->assertEqualsWithDelta(0.75, $ow / $oh, 0.01, "{$w}x{$h}");
            $im = imagecreatefromstring($out);
            $rgb = imagecolorat($im, (int) ($ow / 2), 2);   // the top row is still the red band
            $this->assertGreaterThan(150, ($rgb >> 16) & 0xFF);
        }
        $same = $this->jpeg(600, 800);
        $this->assertSame($same, PrivateImages::cropToRatio($same, 3 / 4));
    }
}
