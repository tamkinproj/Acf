<?php

namespace App\Core\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Private file storage for case documents. The disk is behind a single class so it can move to object storage later
 * without touching callers.
 *
 *  - an allow-list (PDF, JPEG, PNG, WebP): what a file IS is decided by reading it, never by its name or declared type
 *  - the stored name is random and carries no user input; the original name is kept only for display, sanitised
 *  - nothing is ever served from the web root: downloads go through an authorising controller, as attachments
 */
class DocumentStorage
{
    /** detected MIME => canonical extension */
    private const ALLOWED = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** extensions a client may legitimately send for each detected MIME */
    private const EXTENSIONS = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg', 'jpe'], 'image/png' => ['png'], 'image/webp' => ['webp']];

    public function disk()
    {
        return Storage::disk('local');
    }

    /**
     * @return array{path:string,original_name:string,mime:string,size:int,sha256:string}
     *
     * @throws InvalidArgumentException with a message safe to show the user
     */
    public function store(UploadedFile $file, string $foundationId, string $programId, bool $imageOnly = false): array
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('The upload did not complete. Please try again.');
        }
        $real = $file->getRealPath();
        $size = (int) filesize($real);
        if ($size === 0) {
            throw new InvalidArgumentException('The file is empty.');
        }
        if ($size > config('foundation.uploads.document_max_kb') * 1024) {
            throw new InvalidArgumentException('The file is larger than '.(int) (config('foundation.uploads.document_max_kb') / 1024).' MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($real) ?: '';
        if (! isset(self::ALLOWED[$mime])) {
            throw new InvalidArgumentException('Only PDF, JPEG, PNG and WebP files can be uploaded.');
        }
        $claimed = strtolower($file->getClientOriginalExtension());
        if ($claimed !== '' && ! in_array($claimed, self::EXTENSIONS[$mime], true)) {
            throw new InvalidArgumentException('The file name does not match its contents.');
        }
        if ($imageOnly && ! str_starts_with($mime, 'image/')) {
            throw new InvalidArgumentException('A photo must be an image (JPEG, PNG or WebP).');
        }
        if ($mime === 'application/pdf') {
            if (! str_starts_with((string) file_get_contents($real, false, null, 0, 8), '%PDF-')) {
                throw new InvalidArgumentException('This is not a valid PDF file.');
            }
        } else {
            $info = @getimagesize($real);
            if ($info === false || $info[0] < 1 || $info[1] < 1) {
                throw new InvalidArgumentException('This image cannot be read.');
            }
            if ($info[0] * $info[1] > config('foundation.uploads.document_max_pixels')) {
                throw new InvalidArgumentException('This image is too large to process.');
            }
            if (! $this->imageIsComplete($mime, $real, $size)) {
                throw new InvalidArgumentException('This image is incomplete or damaged. Please upload it again.');
            }
        }

        $path = "documents/{$foundationId}/{$programId}/".Str::lower(Str::random(40)).'.'.self::ALLOWED[$mime];
        $stream = fopen($real, 'rb');
        try {
            $this->disk()->put($path, $stream);
        } finally {
            is_resource($stream) && fclose($stream);
        }

        return [
            'path' => $path, 'original_name' => $this->displayName($file->getClientOriginalName(), self::ALLOWED[$mime]),
            'mime' => $mime, 'size' => $size, 'sha256' => hash_file('sha256', $real),
        ];
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && $this->disk()->exists($path);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            $this->disk()->delete($path);
        }
    }

    /** Streams the file to an already-authorised caller. Images may be shown inline; everything else downloads. */
    public function response(string $path, string $name, string $mime, bool $inline = false): Response
    {
        $inline = $inline && str_starts_with($mime, 'image/');
        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.addcslashes(Str::ascii($name) ?: 'document', '"\\').'"; filename*=UTF-8\'\''.rawurlencode($name);

        return response()->stream(function () use ($path) {
            $stream = $this->disk()->readStream($path);
            fpassthru($stream);
            is_resource($stream) && fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $this->disk()->size($path),
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    /** A cheap truncation check that does not decode the picture: each format has a fixed ending. */
    private function imageIsComplete(string $mime, string $path, int $size): bool
    {
        $tail = (string) file_get_contents($path, false, null, max(0, $size - 16));

        return match ($mime) {
            'image/png' => str_ends_with($tail, "IEND\xAE\x42\x60\x82"),
            'image/jpeg' => str_ends_with(rtrim($tail, "\0"), "\xFF\xD9"),
            'image/webp' => (($header = (string) file_get_contents($path, false, null, 0, 12)) !== '' && strlen($header) === 12
                && unpack('V', substr($header, 4, 4))[1] + 8 <= $size),
            default => true,
        };
    }

    /** A safe label: no path parts, no control characters, bounded, with the true extension. */
    private function displayName(string $original, string $extension): string
    {
        $base = pathinfo(str_replace('\\', '/', $original), PATHINFO_FILENAME);
        $base = preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '', $base) ?? '';
        $base = trim(preg_replace('/\.{2,}/', '.', $base) ?? '', " .-_");

        return mb_substr($base !== '' ? $base : 'document', 0, 100).'.'.$extension;
    }
}
