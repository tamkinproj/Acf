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
    public static function store(UploadedFile $file, string $dir): array|string
    {
        $max = (int) config('orphan_fields.photos.max_kb', 4096) * 1024;
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
        if (max($w, $h) > 1400) {
            $r = 1400 / max($w, $h);
            $dst = imagecreatetruecolor((int) round($w * $r), (int) round($h * $r));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            $src = $dst;
        }
        $flat = imagecreatetruecolor(imagesx($src), imagesy($src));       // jpeg has no transparency: put it on white
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        ob_start();
        imagejpeg($flat, null, 84);
        $data = (string) ob_get_clean();
        $path = trim($dir, '/').'/'.bin2hex(random_bytes(16)).'.jpg';
        if ($data === '' || ! Storage::disk('local')->put($path, $data)) {
            return 'The photo could not be saved.';
        }

        return ['path' => $path, 'mime' => 'image/jpeg'];
    }
}
