<?php

namespace App\Core\Foundation;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Safe logo handling. The upload is never stored as sent: its real type is
 * sniffed from content (not the client's filename/MIME), it is decoded and
 * re-encoded through GD (which discards any embedded payload/metadata), and it
 * is saved under a server-generated name - so there is no path traversal and no
 * way to smuggle a script or polyglot file into storage.
 */
class LogoStorage
{
    private const DISK = 'local';          // private: storage/app/private
    private const DIR = 'foundation';
    private const ALLOWED = ['image/png' => 'imagecreatefrompng', 'image/jpeg' => 'imagecreatefromjpeg', 'image/webp' => 'imagecreatefromwebp'];
    private const MAX_SIDE = 512;

    /** @return array{path:string,hash:string} */
    public function store(UploadedFile $file): array
    {
        if (! extension_loaded('gd')) {
            throw new InvalidArgumentException('The server cannot process images (PHP gd extension missing).');
        }
        if (! $file->isValid() || $file->getSize() > (int) config('foundation.uploads.logo_max_kb') * 1024) {
            throw new InvalidArgumentException('The logo must be a valid file under '.config('foundation.uploads.logo_max_kb').' KB.');
        }

        $real = $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($real);
        $info = @getimagesize($real);
        if (! isset(self::ALLOWED[$mime]) || $info === false) {
            throw new InvalidArgumentException('The logo must be a PNG, JPEG or WebP image.');
        }
        $max = (int) config('foundation.uploads.logo_max_pixels');
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > $max || $info[1] > $max) {
            throw new InvalidArgumentException("The logo dimensions must be between 1 and {$max} pixels.");
        }

        // GD signals a corrupt/truncated file with a PHP warning (which Laravel turns into an exception), not a false return.
        set_error_handler(fn () => true);
        try {
            $source = (self::ALLOWED[$mime])($real);
        } finally {
            restore_error_handler();
        }
        if (! $source) {
            throw new InvalidArgumentException('The image could not be read. It may be corrupt.');
        }

        $scale = min(1, self::MAX_SIDE / max($info[0], $info[1]));
        $w = max(1, (int) round($info[0] * $scale));
        $h = max(1, (int) round($info[1] * $scale));
        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        $path = self::DIR.'/logo-'.Str::lower(Str::random(24)).'.png';
        Storage::disk(self::DISK)->put($path, $png);

        return ['path' => $path, 'hash' => hash('sha256', $png)];
    }

    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIR.'/')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return $path !== null && str_starts_with($path, self::DIR.'/') && Storage::disk(self::DISK)->exists($path);
    }

    public function contents(string $path): string
    {
        return Storage::disk(self::DISK)->get($path);
    }
}
