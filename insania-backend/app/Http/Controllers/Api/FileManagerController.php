<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileLibrary;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\StepUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The President's Files page: every file the offices uploaded, in folders with names people know.
 * Look, open, download one file or a whole folder (ZIP), and download a backup of everything to keep offline.
 * Read only: files that belong to a record are changed on that record's own page.
 */
class FileManagerController extends Controller
{
    /** the records written into the backup as spreadsheets (only this foundation's rows; passwords and keys are never written) */
    private const TABLES = [
        'children', 'guardians', 'bank_accounts', 'channel_histories', 'child_documents', 'child_photos', 'child_applications', 'orphan_reports',
        'sponsors', 'sponsorships', 'sponsorship_payments', 'partners', 'projects', 'departments', 'users', 'notices',
        'att_employees', 'att_records', 'att_leaves', 'att_holidays', 'work_activity',
        'edu_institutions', 'edu_curricula', 'edu_units', 'edu_teachers', 'edu_contracts', 'edu_salaries', 'edu_progress',
        'site_contents', 'site_images', 'audit_logs',
    ];

    private const SECRET = '#password|token|secret|remember|two_factor|key_hash|ip_hash|otp|face_descriptor|embedding|signature#i';

    /** GET files?folder=orphans/dar_al_ber&q= : the folders and files in one folder, or everything matching a search */
    public function index(Request $request)
    {
        $data = $request->validate(['folder' => ['nullable', 'string', 'max:300', 'regex:#^[a-z0-9_/-]*$#'], 'q' => ['nullable', 'string', 'max:80']]);
        $lib = new FileLibrary((int) $request->user()->foundation_id);
        $all = $lib->all();

        $q = trim((string) ($data['q'] ?? ''));
        if (mb_strlen($q) >= 2) {
            $needle = mb_strtolower($q);
            $hits = array_values(array_filter($all, fn ($e) => str_contains(mb_strtolower($e['name'].' '.implode(' ', array_column($e['folder'], 1))), $needle)));
            usort($hits, fn ($a, $b) => $b['at'] <=> $a['at']);

            return ApiResponse::ok(['q' => $q, 'files' => array_map(fn ($e) => $this->out($e, true), array_slice($hits, 0, 300)), 'more' => max(0, count($hits) - 300)]);
        }

        $parts = array_values(array_filter(explode('/', (string) ($data['folder'] ?? '')), fn ($p) => $p !== ''));
        $n = count($parts);
        $folders = [];
        $files = [];
        $crumbs = [];
        if ($n === 0) {
            foreach (FileLibrary::SECTIONS as $id => $name) {
                $folders[$id] = ['id' => $id, 'name' => $name, 'count' => 0, 'size' => 0, 'at' => 0];
            }
        }
        foreach ($all as $e) {
            $ids = array_column($e['folder'], 0);
            if (array_slice($ids, 0, $n) !== $parts) {
                continue;
            }
            if (! $crumbs && $n > 0) {
                $crumbs = array_map(fn ($f) => ['id' => $f[0], 'name' => $f[1]], array_slice($e['folder'], 0, $n));
            }
            if (count($ids) === $n) {
                $files[] = $this->out($e);
                continue;
            }
            [$id, $name] = $e['folder'][$n];
            $f = $folders[$id] ?? ['id' => $id, 'name' => $name, 'count' => 0, 'size' => 0, 'at' => 0];
            $f['count']++;
            $f['size'] += $e['size'];
            $f['at'] = max($f['at'], $e['at']);
            $folders[$id] = $f;
        }
        if ($n > 0 && ! $crumbs) {
            return ApiResponse::error('NOT_FOUND', 'This folder is empty or no longer there.', 404);
        }

        $folders = array_values($folders);
        // applications and attendance months: newest first; everything else A to Z
        $desc = $n === 1 && in_array($parts[0], ['applications', 'attendance'], true);
        if ($n > 0) {
            usort($folders, fn ($a, $b) => $desc ? strnatcasecmp($b['name'], $a['name']) : strnatcasecmp($a['name'], $b['name']));
        }
        usort($files, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        $folders = array_map(fn ($f) => $f + ['at_iso' => $f['at'] ? date(DATE_ATOM, $f['at']) : null], $folders);

        $total = ['count' => count($all), 'size' => array_sum(array_column($all, 'size'))];

        return ApiResponse::ok(['folder' => implode('/', $parts), 'crumbs' => $crumbs, 'folders' => $folders, 'files' => $files, 'total' => $total]);
    }

    /** GET files/file?ref=…&download=1 : one file, shown in the app (photo, voice, PDF) or saved */
    public function file(Request $request)
    {
        $data = $request->validate(['ref' => ['required', 'string', 'max:400'], 'download' => ['nullable', 'boolean']]);
        $e = (new FileLibrary((int) $request->user()->foundation_id))->find($data['ref']);
        if (! $e) {
            return ApiResponse::error('NOT_FOUND', 'This file is no longer there.', 404);
        }
        $save = (bool) ($data['download'] ?? false);
        if ($save) {
            Audit::record('files.downloaded', $request, null, null, ['file' => $e['name'], 'folder' => implode(' > ', array_column($e['folder'], 1))]);
        }

        return response()->file(FileLibrary::absolute($e), [
            'Content-Type' => $e['mime'], 'Content-Disposition' => self::disposition($save ? 'attachment' : 'inline', $e['name']),
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }

    /** GET files/zip?folder=…&part=1 : a whole folder as a ZIP, in parts when it is large */
    public function zip(Request $request)
    {
        $data = $request->validate(['folder' => ['nullable', 'string', 'max:300', 'regex:#^[a-z0-9_/-]*$#'], 'part' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        if (! class_exists(\ZipArchive::class)) {
            return ApiResponse::error('NO_ZIP', 'This server cannot make ZIP files. Ask the System Admin to turn on PHP zip.', 500);
        }
        $parts = array_values(array_filter(explode('/', (string) ($data['folder'] ?? '')), fn ($p) => $p !== ''));
        $n = count($parts);
        $lib = new FileLibrary((int) $request->user()->foundation_id);
        $list = array_values(array_filter($lib->all(), fn ($e) => array_slice(array_column($e['folder'], 0), 0, $n) === $parts));
        if (! $list) {
            return ApiResponse::error('NOT_FOUND', 'There is nothing in this folder to download.', 404);
        }
        $title = $n ? $list[0]['folder'][$n - 1][1] : 'All files';
        $chunks = self::chunks($list);
        $part = min((int) ($data['part'] ?? 1), count($chunks));
        // inside the ZIP the folder itself is the top
        $tmp = $this->build($chunks[$part - 1], max(0, $n - 1), '');
        Audit::record('files.zip_downloaded', $request, null, null, ['folder' => $n ? implode(' > ', array_column(array_slice($list[0]['folder'], 0, $n), 1)) : 'All files', 'part' => $part, 'count' => count($chunks[$part - 1])]);

        return $this->send($tmp, FileLibrary::clean($title).' '.date('Y-m-d').(count($chunks) > 1 ? ' part '.$part.' of '.count($chunks) : '').'.zip', count($chunks), count($chunks[$part - 1]));
    }

    /** POST files/backup {confirm_password, part} : every file and every record, to keep offline. Asks for the password. */
    public function backup(Request $request)
    {
        if ($denied = StepUp::check($request)) {
            return $denied;
        }
        $data = $request->validate(['part' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        if (! class_exists(\ZipArchive::class)) {
            return ApiResponse::error('NO_ZIP', 'This server cannot make ZIP files. Ask the System Admin to turn on PHP zip.', 500);
        }
        $fid = (int) $request->user()->foundation_id;
        $all = (new FileLibrary($fid))->all();
        $chunks = $all ? self::chunks($all) : [[]];
        $part = min((int) ($data['part'] ?? 1), count($chunks));
        $tables = [];
        $tmp = $this->build($chunks[$part - 1], 0, 'files/', function (\ZipArchive $zip, array &$temp) use ($part, $fid, $request, $all, $chunks, &$tables) {
            if ($part !== 1) {
                return;
            }
            $tables = $this->data($zip, $temp, $fid);
            $zip->addFromString('READ ME.txt', $this->readme($request, $all, $tables, count($chunks)));
        });
        Audit::record('files.backup_downloaded', $request, null, null, ['part' => $part, 'parts' => count($chunks), 'files' => count($chunks[$part - 1]), 'tables' => count($tables)]);

        return $this->send($tmp, 'Al Insania backup '.date('Y-m-d').(count($chunks) > 1 ? ' part '.$part.' of '.count($chunks) : '').'.zip', count($chunks), count($chunks[$part - 1]));
    }

    /* ------------------------------------------------------------------ helpers */

    private function out(array $e, bool $where = false): array
    {
        $o = ['ref' => $e['ref'], 'name' => $e['name'], 'size' => $e['size'], 'at' => date(DATE_ATOM, $e['at']), 'mime' => $e['mime'], 'kind' => FileLibrary::kind($e['mime']), 'by' => $e['by']];
        if ($where) {
            $o['folder'] = implode('/', array_column($e['folder'], 0));
            $o['where'] = implode(' › ', array_column($e['folder'], 1));
        }

        return $o;
    }

    /** split into ZIPs of at most files.part_mb each (a phone and a small server can both handle that) */
    private static function chunks(array $list): array
    {
        usort($list, fn ($a, $b) => [implode('/', array_column($a['folder'], 1)), $a['name']] <=> [implode('/', array_column($b['folder'], 1)), $b['name']]);
        $limit = max(1, (int) config('files.part_mb', 150)) * 1024 * 1024;
        $out = [[]];
        $size = 0;
        foreach ($list as $e) {
            if ($out[count($out) - 1] && $size + $e['size'] > $limit) {
                $out[] = [];
                $size = 0;
            }
            $out[count($out) - 1][] = $e;
            $size += $e['size'];
        }

        return $out;
    }

    private function build(array $list, int $drop, string $prefix, ?callable $extra = null): string
    {
        @set_time_limit(300);
        $tmp = tempnam(sys_get_temp_dir(), 'fm');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $temp = [];
        foreach ($list as $e) {
            $dirs = array_map(fn ($f) => FileLibrary::clean($f[1]) ?: $f[0], array_slice($e['folder'], $drop));
            $name = $prefix.implode('/', array_merge($dirs, [$e['name']]));
            $zip->addFile(FileLibrary::absolute($e), $name, 0, 0, \ZipArchive::FL_ENC_UTF_8);   // Arabic names marked as UTF-8
            $zip->setCompressionName($name, \ZipArchive::CM_STORE);   // photos, voice and PDFs are compressed already
        }
        if ($extra) {
            $extra($zip, $temp);
        }
        $zip->close();
        foreach ($temp as $t) {
            @unlink($t);
        }

        return $tmp;
    }

    private function send(string $tmp, string $name, int $parts, int $count)
    {
        return response()->download($tmp, $name, [
            'Content-Type' => 'application/zip', 'Content-Disposition' => self::disposition('attachment', $name), 'Cache-Control' => 'private, no-store',
            'X-Parts' => (string) $parts, 'X-Count' => (string) $count, 'Access-Control-Expose-Headers' => 'X-Parts, X-Count, Content-Disposition',
        ])->deleteFileAfterSend(true);
    }

    private static function disposition(string $type, string $name): string
    {
        $plain = trim(preg_replace('#\s+#', ' ', preg_replace('#[^\x20-\x7E]|[%/\\\\"]#', ' ', $name)));
        if ($plain === '' || str_starts_with($plain, '.')) {
            $plain = 'file'.(pathinfo($name, PATHINFO_EXTENSION) ? '.'.pathinfo($name, PATHINFO_EXTENSION) : '');
        }

        return HeaderUtils::makeDisposition($type, $name, $plain);
    }

    /** the records as spreadsheets in data/ ; returns rows per table */
    private function data(\ZipArchive $zip, array &$temp, int $fid): array
    {
        $counts = [];
        foreach (self::TABLES as $t) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, 'foundation_id')) {
                continue;
            }
            $cols = array_values(array_filter(Schema::getColumnListing($t), fn ($c) => ! preg_match(self::SECRET, $c)));
            $file = tempnam(sys_get_temp_dir(), 'csv');
            $temp[] = $file;
            $fh = fopen($file, 'w');
            fwrite($fh, "\xEF\xBB\xBF".implode(',', array_map([Csv::class, 'cell'], $cols))."\r\n");
            $rows = 0;
            $query = DB::table($t)->where('foundation_id', $fid)->select($cols);
            $each = in_array('id', $cols, true) ? $query->lazyById(500) : $query->orderBy($cols[0])->cursor();
            foreach ($each as $row) {
                $row = (array) $row;
                fwrite($fh, implode(',', array_map(fn ($c) => Csv::cell(is_scalar($row[$c]) || $row[$c] === null ? $row[$c] : json_encode($row[$c], JSON_UNESCAPED_UNICODE)), $cols))."\r\n");
                $rows++;
            }
            fclose($fh);
            $zip->addFile($file, 'data/'.$t.'.csv');
            $counts[$t] = $rows;
        }

        return $counts;
    }

    private function readme(Request $request, array $all, array $tables, int $parts): string
    {
        $by = [];
        foreach ($all as $e) {
            $by[$e['folder'][0][1]] = ($by[$e['folder'][0][1]] ?? 0) + 1;
        }
        $lines = [
            'Al Insania Charity Foundation - backup',
            'Made: '.now()->format('Y-m-d H:i').' by '.$request->user()->name,
            $parts > 1 ? "This backup has $parts ZIP files. Keep all of them together." : 'This backup is one ZIP file.',
            '',
            'files/  every file uploaded in the offices, in the same folders as the Files page:',
        ];
        foreach ($by as $k => $v) {
            $lines[] = '   '.$k.': '.$v.' files';
        }
        $lines[] = '';
        $lines[] = 'data/  the records as spreadsheets (open them with Excel or Google Sheets):';
        foreach ($tables as $t => $n) {
            $lines[] = '   '.$t.'.csv: '.$n.' rows';
        }
        $lines[] = '';
        $lines[] = 'Not included: passwords, sign-in keys, and private chats between two people.';
        $lines[] = 'Keep this backup safe. It holds personal details of orphans, families and staff.';

        return implode("\r\n", $lines)."\r\n";
    }
}
