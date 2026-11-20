<?php

namespace App\Support;

use App\Models\SiteImage;
use Illuminate\Http\UploadedFile;

/**
 * Photos for the public page. They are decoded and saved again (so nothing hidden in the file survives),
 * shrunk, renamed to a random name, and written under the website's own uploads folder.
 */
final class SiteImages
{
    public static function dir(): string
    {
        return rtrim((string) config('site.upload_dir', public_path('uploads/site')), '/');
    }

    /** @return SiteImage|string the saved image, or a plain-language reason */
    public static function store(UploadedFile $file, int $foundationId, ?int $userId): SiteImage|string
    {
        $limits = config('site.limits');
        if (! $file->isValid() || $file->getSize() > $limits['image_bytes']) {
            return 'The photo is too large (maximum '.round($limits['image_bytes'] / 1048576).' MB).';
        }
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? '';
        if (! $info || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 'Use a JPG, PNG or WebP photo.';
        }
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w > 9000 || $h > 9000) {
            return 'The photo dimensions are not supported.';
        }
        if (! function_exists('imagecreatefromstring')) {
            return 'This server cannot process photos (PHP GD is off). Turn on the "gd" extension in hPanel.';
        }
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return 'The photo could not be read.';
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {          // phone photos: turn them upright
            $o = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            $src = match ($o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
            [$w, $h] = [imagesx($src), imagesy($src)];
        }

        $max = $limits['image_side'];
        if (max($w, $h) > $max) {
            $r = $max / max($w, $h);
            $dst = imagecreatetruecolor((int) round($w * $r), (int) round($h * $r));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            $src = $dst;
            [$w, $h] = [imagesx($src), imagesy($src)];
        }

        $dir = self::dir();
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            return 'The uploads folder could not be created. Check that the website folder can be written.';
        }
        // a safety net: nothing in this folder may ever be run as a program
        @file_put_contents($dir.'/.htaccess', "Options -Indexes -ExecCGI\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi)$\">\n  Require all denied\n</FilesMatch>\n");

        imagealphablending($src, false);
        imagesavealpha($src, true);                                   // keep transparent logos transparent
        $webp = function_exists('imagewebp');
        $name = bin2hex(random_bytes(12)).($webp ? '.webp' : '.jpg');
        ob_start();
        $ok = $webp ? imagewebp($src, null, 82) : imagejpeg($src, null, 85);
        $data = (string) ob_get_clean();
        if (! $ok || $data === '' || file_put_contents("$dir/$name", $data) === false) {
            return 'The photo could not be saved.';
        }
        @chmod("$dir/$name", 0644);

        return SiteImage::withoutGlobalScopes()->create(['foundation_id' => $foundationId, 'filename' => $name, 'mime' => $webp ? 'image/webp' : 'image/jpeg',
            'width' => $w, 'height' => $h, 'bytes' => strlen($data), 'uploaded_by' => $userId]);
    }

    /** @param array<int|string, mixed> $ids */
    public static function urls(array $ids, int $foundationId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }

        return SiteImage::withoutGlobalScopes()->where('foundation_id', $foundationId)->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (SiteImage $i) => [$i->id => ['url' => $i->url(), 'width' => $i->width, 'height' => $i->height]])->all();
    }
}
