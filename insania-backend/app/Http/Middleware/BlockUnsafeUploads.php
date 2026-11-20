<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Refuses any uploaded file that a browser or the web server could treat
 * as a page or a script, on every route, before a controller sees it.
 *
 * Uploads are saved under public/assets/ on the same domain as the web
 * app, some named after the uploader's own file name and some after the
 * type guessed from the content (UploadedFile::extension()). A web page or
 * SVG saved there would run in the web app's origin. Checking here covers
 * every upload endpoint at once, old and new, instead of relying on each
 * controller's own validation rules.
 *
 * A file is refused when its content looks like one of these types, when
 * its name ends in one of these extensions, or when any earlier part of
 * its name is a server-side script extension ("report.php.jpg" - some
 * servers run those). Server config file names (.htaccess, .user.ini,
 * web.config) are refused outright.
 * Photos, PDFs, Office documents, spreadsheets, CSV, audio and video are
 * unaffected.
 */
class BlockUnsafeUploads
{
    /** Extensions a web server may execute - refused anywhere in the name. */
    private const SERVER_SCRIPT_EXTENSIONS = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
        'cgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp', 'jspx', 'shtml', 'shtm',
    ];

    /** Extensions a browser renders as a page or runs - refused as the final extension. */
    private const BLOCKED_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'xht', 'shtml', 'shtm', 'mht', 'mhtml', 'hta',
        'svg', 'svgz', 'xml', 'xsl', 'xslt', 'js', 'mjs', 'swf',
        'exe', 'dll', 'bat', 'cmd', 'msi', 'vbs', 'ps1', 'scr',
    ];

    /** File names that change how the server treats a folder. */
    private const BLOCKED_NAMES = ['.htaccess', '.htpasswd', '.user.ini', 'web.config', 'php.ini'];

    /** Content types (as detected from the bytes) that are never accepted. */
    private const BLOCKED_MIME_PATTERNS = [
        '#^text/html#', '#xhtml#', '#^image/svg#', '#^(text|application)/xml#', '#\+xml$#',
        '#javascript#', '#ecmascript#', '#php#', '#^text/x-(shellscript|perl|python|ruby)#',
        '#^application/x-(sh|csh|executable|msdownload|dosexec|shockwave)#',
    ];

    public function handle(Request $request, Closure $next)
    {
        foreach ($this->files($request->allFiles()) as $field => $file) {
            if ($reason = self::problem($file)) {
                return \App\Support\ApiResponse::error(
                    'UNSAFE_FILE',
                    'This file type is not allowed. Upload a photo (JPG/PNG) or a PDF.',
                    422,
                    [$field => [$reason]]
                );
            }
        }

        return $next($request);
    }

    /** Why this file is refused, or null when it's fine. */
    public static function problem(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return null; // Laravel's own validation reports failed uploads.
        }

        $name = strtolower(trim(basename(str_replace('\\', '/', (string) $file->getClientOriginalName()))));
        if (in_array($name, self::BLOCKED_NAMES, true)) {
            return 'This file name is not allowed.';
        }
        $parts = array_map('trim', array_slice(explode('.', $name), 1));
        $last = $parts === [] ? '' : end($parts);
        if (in_array($last, self::BLOCKED_EXTENSIONS, true) || in_array($last, self::SERVER_SCRIPT_EXTENSIONS, true)) {
            return 'Files of type .' . $last . ' are not allowed.';
        }
        foreach ($parts as $part) {
            if (in_array($part, self::SERVER_SCRIPT_EXTENSIONS, true)) {
                return 'Files of type .' . $part . ' are not allowed.';
            }
        }

        $guessed = strtolower((string) $file->guessExtension());
        if ($guessed !== '' && (in_array($guessed, self::BLOCKED_EXTENSIONS, true) || in_array($guessed, self::SERVER_SCRIPT_EXTENSIONS, true))) {
            return 'This file\'s content is not an allowed type.';
        }

        $mime = strtolower((string) $file->getMimeType());
        foreach (self::BLOCKED_MIME_PATTERNS as $pattern) {
            if (preg_match($pattern, $mime)) {
                return 'This file\'s content is not an allowed type.';
            }
        }

        return null;
    }

    /** @return iterable<string, UploadedFile> every uploaded file, including ones in arrays (images[]). */
    private function files(array $files, string $prefix = ''): iterable
    {
        foreach ($files as $key => $value) {
            $field = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if ($value instanceof UploadedFile) {
                yield $field => $value;
            } elseif (is_array($value)) {
                yield from $this->files($value, $field);
            }
        }
    }
}
