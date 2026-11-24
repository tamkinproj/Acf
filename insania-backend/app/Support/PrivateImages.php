<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Photos of orphans. Private: stored under storage/app (never the website folder), decoded and saved again so nothing hidden in the
 * file survives, shrunk, and given a random name. Only signed-in staff with the right can look at them.
 */
final class PrivateImages
{
    /** @return array{path: string, mime: string}|string a stored file, or a plain-language reason */
    /** $ratio: width / height the photo must have (within 3%), for example 3 / 4; empty: any shape. */
    public static function store(UploadedFile $file, string $dir, int $maxSide = 1400, ?int $maxKb = null, ?float $ratio = null): array|string
    {
        $max = ($maxKb ?? (int) config('orphan_fields.photos.max_kb', 4096)) * 1024;
        if (! $file->isValid() || $file->getSize() > $max) {
            return 'The photo is too large (maximum '.round($max / 1048576).' MB).';
        }
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] < 1 || $info[1] < 1 || $info[0] > 9000 || $info[1] > 9000) {
            return 'Use a JPG, PNG or WebP photo.';
        }
        if (! function_exists('imagecreatefromstring')) {
            return 'This server cannot process photos (PHP GD is off).';
        }
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return 'The photo could not be read.';
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        if (($info['mime'] === 'image/jpeg') && function_exists('exif_read_data')) {
            $o = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            $src = match ($o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
            [$w, $h] = [imagesx($src), imagesy($src)];
        }
        if ($ratio !== null && abs(($w / $h) - $ratio) / $ratio > 0.03) {
            return 'The photo must be upright, 3 wide by 4 tall (the same shape as the box in the report). The app crops it for you; crop it first if you use another program.';
        }
        if (max($w, $h) > $maxSide) {
            $r = $maxSide / max($w, $h);
            $dst = imagecreatetruecolor((int) round($w * $r), (int) round($h * $r));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            $src = $dst;
        }
        $flat = imagecreatetruecolor(imagesx($src), imagesy($src));       // jpeg has no transparency: put it on white
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        ob_start();
        imagejpeg($flat, null, $maxSide > 1400 ? 88 : 84);
        $data = (string) ob_get_clean();
        $path = trim($dir, '/').'/'.bin2hex(random_bytes(16)).'.jpg';
        if ($data === '' || ! Storage::disk('local')->put($path, $data)) {
            return 'The photo could not be saved.';
        }

        return ['path' => $path, 'mime' => 'image/jpeg'];
    }

    /** A JPEG cut to width / height = $ratio, keeping the top (faces stay in view) and the middle from side to side. Already that shape: unchanged. */
    public static function cropToRatio(string $jpeg, float $ratio): string
    {
        $info = @getimagesizefromstring($jpeg);
        if (! $info || ! function_exists('imagecreatefromstring')) {
            return $jpeg;
        }
        [$w, $h] = [$info[0], $info[1]];
        if (abs(($w / $h) - $ratio) / $ratio <= 0.01) {
            return $jpeg;
        }
        $src = @imagecreatefromstring($jpeg);
        if (! $src) {
            return $jpeg;
        }
        if ($w / $h > $ratio) {
            $cw = (int) round($h * $ratio);
            $rect = ['x' => (int) floor(($w - $cw) / 2), 'y' => 0, 'width' => $cw, 'height' => $h];
        } else {
            $ch = (int) round($w / $ratio);
            $rect = ['x' => 0, 'y' => 0, 'width' => $w, 'height' => min($h, $ch)];
        }
        $out = imagecrop($src, $rect);
        if (! $out) {
            return $jpeg;
        }
        ob_start();
        imagejpeg($out, null, 88);

        return (string) ob_get_clean() ?: $jpeg;
    }
}
