<?php

namespace App\Services;

use App\Models\Attendance\Employee;
use App\Models\Attendance\Record;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Child;
use App\Models\ChildApplication;
use App\Models\ChildDocument;
use App\Models\ChildPhoto;
use App\Models\Partner;
use App\Models\SiteImage;
use App\Models\User;
use App\Services\OrphanReports\ReportTemplates;
use App\Support\SiteImages;
use Illuminate\Support\Facades\Storage;

/**
 * Every file the offices uploaded through the app, in folders people recognise
 * (Orphans > Dar Al Ber Society > 150409 - Rania Malang > Birth certificate.jpg), for the President's Files page and the backup.
 *
 * Built from what is really on the disk, named from the records: a file that no record points to still shows, with its own name.
 * Private chats between two people are left out. Nothing here changes or deletes a file.
 */
final class FileLibrary
{
    /** the top folders, in this order */
    public const SECTIONS = [
        'orphans' => 'Orphans', 'applications' => 'Applications', 'chat' => 'Group chats', 'attendance' => 'Attendance',
        'staff' => 'Staff photos', 'forms' => 'Report forms', 'website' => 'Website images',
    ];

    private const MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'pdf' => 'application/pdf', 'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'm4a' => 'audio/mp4',
        'mp4' => 'audio/mp4', 'aac' => 'audio/aac', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
    ];

    /** @var list<array>|null */
    private ?array $all = null;

    /** @var array<int, string> */
    private array $users = [];

    public function __construct(private int $fid)
    {
    }

    /**
     * @return list<array{folder: list<array{0: string, 1: string}>, name: string, ref: string, disk: string, path: string, size: int, at: int, mime: string, by: ?string}>
     */
    public function all(): array
    {
        if ($this->all !== null) {
            return $this->all;
        }
        $this->users = User::withoutGlobalScopes()->where('foundation_id', $this->fid)->pluck('name', 'id')->all();
        $all = array_merge($this->orphans(), $this->applications(), $this->chat(), $this->attendance(), $this->staff(), $this->forms(), $this->website());

        // two files with the same name in one folder: the second becomes "name (2).jpg"
        $seen = [];
        foreach ($all as &$e) {
            $key = implode('/', array_column($e['folder'], 0)).'/'.mb_strtolower($e['name']);
            if (isset($seen[$key])) {
                $n = ++$seen[$key];
                $dot = strrpos($e['name'], '.');
                $e['name'] = $dot === false ? $e['name']." ($n)" : substr($e['name'], 0, $dot)." ($n)".substr($e['name'], $dot);
            } else {
                $seen[$key] = 1;
            }
        }
        unset($e);

        return $this->all = $all;
    }

    /** One file by its ref ("local:children/2/31/abc.jpg"), only if it is in the library. */
    public function find(string $ref): ?array
    {
        foreach ($this->all() as $e) {
            if ($e['ref'] === $ref) {
                return $e;
            }
        }

        return null;
    }

    /** The file on the disk. */
    public static function absolute(array $e): string
    {
        return $e['disk'] === 'site' ? SiteImages::dir().'/'.basename($e['path']) : Storage::disk('local')->path($e['path']);
    }

    /** Text that is safe as a file or folder name on every computer and phone. */
    public static function clean(?string $s, int $max = 110): string
    {
        $s = preg_replace('#[\\\\/:*?"<>|\x00-\x1F\x7F\x{200E}\x{200F}\x{202A}-\x{202E}]+#u', ' ', (string) $s);
        $s = trim(preg_replace('#\s+#u', ' ', (string) $s), " .\t");

        return mb_substr($s, 0, $max);
    }

    public static function kind(string $mime): string
    {
        return str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'audio/') ? 'audio' : ($mime === 'application/pdf' ? 'pdf' : 'other'));
    }

    /* ------------------------------------------------------------------ the folders */

    private function orphans(): array
    {
        $files = $this->files("children/{$this->fid}");
        if (! $files) {
            return [];
        }
        $byChild = [];
        foreach ($files as $p) {
            $byChild[(int) (explode('/', $p)[2] ?? 0)][] = $p;
        }
        $kids = Child::withoutGlobalScopes()->where('foundation_id', $this->fid)->whereIn('id', array_keys($byChild))->get(['id', 'partner_id', 'ref', 'external_no', 'name_en', 'name_ar'])->keyBy('id');
        $partners = Partner::withoutGlobalScopes()->where('foundation_id', $this->fid)->get(['id', 'code', 'name_en'])->keyBy('id');

        $names = [];
        $photos = ChildPhoto::withoutGlobalScopes()->where('foundation_id', $this->fid)->orderBy('id')->get(['child_id', 'path']);
        $count = [];
        foreach ($photos as $ph) {
            $count[$ph->child_id] = ($count[$ph->child_id] ?? 0) + 1;
            $names[$ph->path] = ['Photo '.$count[$ph->child_id], null];
        }
        foreach (ChildDocument::withoutGlobalScopes()->where('foundation_id', $this->fid)->whereNotNull('path')->get(['type', 'path', 'uploaded_by']) as $d) {
            $names[$d->path] = [self::typeName($d->type), $d->uploaded_by];
        }

        $out = [];
        foreach ($byChild as $id => $paths) {
            $c = $kids->get($id);
            $p = $c ? $partners->get($c->partner_id) : null;
            $label = $c ? self::clean(implode(' - ', array_filter([$c->external_no ?: $c->ref, self::clean($c->name_en), $c->name_ar !== $c->name_en ? self::clean($c->name_ar) : '']))) : 'Removed orphan '.$id;
            $folder = [['orphans', self::SECTIONS['orphans']], [$p ? $p->code : 'none', $p ? $p->name_en : 'No partner'], ['c'.$id, $label ?: 'Orphan '.$id]];
            foreach ($paths as $path) {
                [$name, $by] = $names[$path] ?? [null, null];
                $out[] = $this->entry($folder, $path, $name, null, $by);
            }
        }

        return array_values(array_filter($out));
    }

    private function applications(): array
    {
        $ids = array_values(array_filter(array_map(fn ($d) => ctype_digit(basename($d)) ? (int) basename($d) : 0, Storage::disk('local')->directories('applications'))));
        if (! $ids) {
            return [];
        }
        $out = [];
        $apps = ChildApplication::withoutGlobalScopes()->where('foundation_id', $this->fid)->whereIn('id', $ids)->get(['id', 'status', 'data', 'photos', 'documents']);
        foreach ($apps as $a) {
            $names = [];
            foreach (array_values((array) $a->photos) as $i => $ph) {
                if (is_array($ph) && isset($ph['path'])) {
                    $names[$ph['path']] = 'Photo '.($i + 1);
                }
            }
            $docN = [];
            foreach ((array) $a->documents as $d) {
                if (is_array($d) && isset($d['path'])) {
                    $t = self::typeName($d['type'] ?? 'other');
                    $docN[$t] = ($docN[$t] ?? 0) + 1;
                    $names[$d['path']] = $t.($docN[$t] > 1 ? ' '.$docN[$t] : '');
                }
            }
            $data = (array) $a->data;
            $label = self::clean(implode(' - ', array_filter([$a->reference(), self::clean($data['name_en'] ?? ''), self::clean($data['name_ar'] ?? '')])).' ('.$a->status.')');
            $folder = [['applications', self::SECTIONS['applications']], ['a'.$a->id, $label]];
            foreach ($this->files('applications/'.$a->id) as $path) {
                $out[] = $this->entry($folder, $path, $names[$path] ?? null);
            }
        }

        return array_values(array_filter($out));
    }

    private function chat(): array
    {
        $out = [];
        $convs = ChatConversation::withoutGlobalScopes()->where('foundation_id', $this->fid)->where('type', '!=', 'direct')->get(['id', 'type', 'title', 'cover_path']);
        foreach ($convs as $c) {
            $paths = $this->files("chat/{$this->fid}/{$c->id}");
            if (! $paths) {
                continue;
            }
            $names = $c->cover_path ? [$c->cover_path => ['Group photo', null, null]] : [];
            $msgs = ChatMessage::withoutGlobalScopes()->where('foundation_id', $this->fid)->where('conversation_id', $c->id)->whereNotNull('file_path')->get(['file_path', 'file_kind', 'file_mime', 'user_id', 'created_at']);
            foreach ($msgs as $m) {
                $who = $this->users[$m->user_id] ?? 'Someone';
                $names[$m->file_path] = [$m->created_at?->format('Y-m-d H.i').' - '.$who.' - '.($m->file_kind === 'audio' ? 'voice message' : 'photo'), $m->file_kind === 'audio' ? $m->file_mime : null, $m->user_id];
            }
            $folder = [['chat', self::SECTIONS['chat']], ['g'.$c->id, $c->type === 'general' ? 'General' : (self::clean($c->title) ?: 'Group '.$c->id)]];
            foreach ($paths as $path) {
                [$name, $mime, $by] = $names[$path] ?? [null, null, null];
                $out[] = $this->entry($folder, $path, $name, $mime, $by);
            }
        }

        return array_values(array_filter($out));
    }

    private function attendance(): array
    {
        $files = $this->files("attendance/{$this->fid}");
        if (! $files) {
            return [];
        }
        $emps = Employee::withoutGlobalScopes()->where('foundation_id', $this->fid)->get(['id', 'name_en', 'face_path'])->keyBy('id');
        $names = [];
        foreach ($emps as $e) {
            if ($e->face_path) {
                $names[$e->face_path] = self::clean($e->name_en);
            }
        }
        $recs = Record::withoutGlobalScopes()->where('foundation_id', $this->fid)->where(fn ($q) => $q->whereNotNull('in_photo')->orWhereNotNull('out_photo'))
            ->get(['employee_id', 'work_date', 'clock_in_at', 'clock_out_at', 'in_photo', 'out_photo']);
        foreach ($recs as $r) {
            $who = self::clean($emps->get($r->employee_id)?->name_en) ?: 'Employee '.$r->employee_id;
            if ($r->in_photo) {
                $names[$r->in_photo] = ($r->clock_in_at?->format('Y-m-d H.i') ?? $r->work_date?->format('Y-m-d')).' - '.$who.' - in';
            }
            if ($r->out_photo) {
                $names[$r->out_photo] = ($r->clock_out_at?->format('Y-m-d H.i') ?? $r->work_date?->format('Y-m-d')).' - '.$who.' - out';
            }
        }
        $out = [];
        foreach ($files as $path) {
            $sub = explode('/', $path)[2] ?? '';
            $folder = [['attendance', self::SECTIONS['attendance']], $sub === 'faces' ? ['faces', 'Face photos'] : ['m'.preg_replace('#[^0-9-]#', '', $sub), $sub]];
            $out[] = $this->entry($folder, $path, $names[$path] ?? null);
        }

        return array_values(array_filter($out));
    }

    private function staff(): array
    {
        $files = $this->files("avatars/{$this->fid}");
        if (! $files) {
            return [];
        }
        $names = [];
        foreach (User::withoutGlobalScopes()->where('foundation_id', $this->fid)->whereNotNull('avatar_path')->get(['id', 'name', 'avatar_path']) as $u) {
            $names[$u->avatar_path] = [self::clean($u->name), $u->id];
        }
        $out = [];
        foreach ($files as $path) {
            [$name, $by] = $names[$path] ?? [null, null];
            $out[] = $this->entry([['staff', self::SECTIONS['staff']]], $path, $name, null, $by);
        }

        return array_values(array_filter($out));
    }

    private function forms(): array
    {
        $files = $this->files("partner-forms/{$this->fid}");
        if (! $files) {
            return [];
        }
        $partners = Partner::withoutGlobalScopes()->where('foundation_id', $this->fid)->get(['code', 'name_en'])->keyBy('code');
        $names = [];
        foreach ($partners as $code => $p) {
            foreach (ReportTemplates::info($this->fid, $code)['versions'] as $i => $v) {
                if (isset($v['path'])) {
                    $when = isset($v['uploaded_at']) ? date('Y-m-d H.i', strtotime((string) $v['uploaded_at'])) : '';
                    $names[$v['path']] = [self::clean(trim('Report form '.$when.($i === 0 ? ' (in use)' : ''))), $v['uploaded_by'] ?? null];
                }
            }
        }
        $out = [];
        foreach ($files as $path) {
            $code = explode('/', $path)[2] ?? '';
            [$name, $by] = $names[$path] ?? [null, null];
            $out[] = $this->entry([['forms', self::SECTIONS['forms']], [$code, $partners->get($code)?->name_en ?? $code]], $path, $name, 'application/pdf', $by);
        }

        return array_values(array_filter($out));
    }

    private function website(): array
    {
        $out = [];
        $dir = SiteImages::dir();
        foreach (SiteImage::withoutGlobalScopes()->where('foundation_id', $this->fid)->orderBy('id')->get(['filename', 'mime', 'uploaded_by']) as $img) {
            $abs = $dir.'/'.basename($img->filename);
            if (! is_file($abs)) {
                continue;
            }
            $out[] = ['folder' => [['website', self::SECTIONS['website']]], 'name' => self::clean($img->filename), 'ref' => 'site:'.basename($img->filename), 'disk' => 'site', 'path' => basename($img->filename),
                'size' => (int) filesize($abs), 'at' => (int) filemtime($abs), 'mime' => $img->mime ?: 'image/jpeg', 'by' => $this->users[$img->uploaded_by] ?? null];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ helpers */

    /** @return list<string> every file under a folder of the private disk */
    private function files(string $dir): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($dir)) {
            return [];
        }

        return array_values(array_filter($disk->allFiles($dir), fn ($p) => ! str_starts_with(basename($p), '.')));
    }

    private function entry(array $folder, string $path, ?string $name, ?string $mime = null, ?int $by = null): ?array
    {
        $abs = Storage::disk('local')->path($path);
        if (! is_file($abs)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $mime ?: (self::MIME[$ext] ?? 'application/octet-stream');
        $name = $name !== null && $name !== '' ? $name.($ext !== '' ? '.'.$ext : '') : basename($path);

        return ['folder' => $folder, 'name' => self::clean($name, 140), 'ref' => 'local:'.$path, 'disk' => 'local', 'path' => $path,
            'size' => (int) filesize($abs), 'at' => (int) filemtime($abs), 'mime' => $mime, 'by' => $by ? ($this->users[$by] ?? null) : null];
    }

    private static function typeName(?string $type): string
    {
        $t = str_replace('_', ' ', (string) $type);

        return $t === '' ? 'Document' : mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1);
    }
}
