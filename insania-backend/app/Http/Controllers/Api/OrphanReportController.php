<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\OrphanReport;
use App\Models\User;
use App\Services\OrphanReports\DarAlBerReport;
use App\Services\OrphanReports\ReportBook;
use App\Services\OrphanReports\ReportPhrases;
use App\Services\OrphanReports\ReportTemplates;
use App\Services\OrphanReports\ReportTexts;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\PartnerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Dar Al Ber's periodic orphan report: the year's details for one orphan, its PDF, and many PDFs at once.
 * Seeing and downloading needs children.view; writing and approving needs children.update.
 */
class OrphanReportController extends Controller
{
    private const LABELS = [
        'health' => ['good' => 'Good', 'sick' => 'Sick', 'determined' => 'Person of determination'],
        'stage' => ['kindergarten' => 'Kindergarten', 'primary' => 'Primary', 'intermediate' => 'Intermediate', 'secondary' => 'Secondary', 'university' => 'University / higher', 'none' => 'Not studying', 'child' => 'Young child', 'quran' => 'Memorizing the Quran'],
        'grade' => ['k1' => 'Kindergarten 1', 'k2' => 'Kindergarten 2', 'higher' => 'Higher education'],
        'level' => ['excellent' => 'Excellent ★★★', 'average' => 'Average ★★', 'acceptable' => 'Acceptable ★'],
        'commitment' => ['excellent' => 'Excellent', 'good' => 'Good', 'learning' => 'Learning', 'child' => 'Young child'],
    ];

    /** Only a partner whose report form the system knows has reports. */
    private function guard(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (PartnerContext::current()?->code !== DarAlBerReport::PARTNER) {
            return ApiResponse::error('NO_REPORT', 'This partner has no report form in the system.', 404);
        }

        return null;
    }

    private function year(Request $request): int
    {
        $y = (int) $request->input('year', now()->year);

        return $y >= 2000 && $y <= 2100 ? $y : (int) now()->year;
    }

    private function book(Request $request, int $year): ReportBook
    {
        $info = ReportTemplates::info((int) $request->user()->foundation_id, DarAlBerReport::PARTNER);

        return new ReportBook($year, ['org_name' => $info['org_name'], 'country' => $info['country']]);
    }

    private function options(): array
    {
        $out = [];
        foreach (DarAlBerReport::CHOICES as $k => $list) {
            foreach ($list as $key => $ar) {
                $out[$k][] = [(string) $key, $ar, self::LABELS[$k][$key] ?? ($k === 'grade' ? 'Grade '.$key : $key)];
            }
        }

        return $out;
    }

    private function form(Request $request): array
    {
        $cur = ReportTemplates::current((int) $request->user()->foundation_id, DarAlBerReport::PARTNER);

        return ['available' => (bool) $cur, 'uploaded_at' => $cur['uploaded_at'] ?? null, 'name' => $cur['name'] ?? null];
    }

    private function view(Request $request, Child $child, ReportBook $book): array
    {
        $r = $book->report($child);
        $texts = $book->textsFor($child);
        $f = $book->facts($child);
        $base = DarAlBerReport::facts($child, null, $book->year, $book->settings);   // what the record alone says
        $missing = [];
        foreach (['name' => 'name', 'file_no' => 'file_no', 'birth_date' => 'birth_date', 'gender' => 'gender', 'sponsor_name' => 'sponsor_name', 'responsible_name' => 'responsible_name'] as $k => $label) {
            if (! $f[$k]) {
                $missing[] = $label;
            }
        }
        if (! $child->photos()->exists()) {
            $missing[] = 'photo';
        }
        $others = ReportTexts::forYear($book->year, $child->id);
        $dup = array_values(array_filter(ReportTexts::KINDS, fn ($k) => $r && trim((string) $r->{$k}) !== '' && $others->taken($k, (string) $r->{$k})));

        return [
            'year' => $book->year,
            'status' => $r ? $r->status : 'new',
            'approved_at' => $r?->approved_at?->toIso8601String(),
            'approved_by' => $r?->approved_by ? User::withoutGlobalScopes()->whereKey($r->approved_by)->value('name') : null,
            'updated_at' => $r?->updated_at?->toIso8601String(),
            'form' => $this->form($request),
            'values' => [
                'responsible_name' => $r?->responsible_name, 'responsible_relation' => $r?->responsible_relation,
                'choices' => (object) array_intersect_key((array) ($r?->choices ?? []), DarAlBerReport::CHOICES),
                'changes' => $texts['changes'], 'comment' => $texts['comment'], 'message' => $texts['message'], 'codes' => $texts['codes'],
                'written' => $texts['written'],
            ],
            'auto' => ['responsible_name' => $base['responsible_name'], 'responsible_relation' => $base['responsible_relation'], 'choices' => $f['auto']],
            'preview' => array_intersect_key($f, array_flip(['org_name', 'country', 'sponsor_name', 'sponsor_file_no', 'name', 'file_no', 'birth_date', 'age', 'gender', 'nationality', 'quran', 'health_note', 'education_note'])),
            'missing' => $missing,
            'same_as_other' => $dup,
            'options' => $this->options(),
            'limits' => ReportPhrases::MAX,
        ];
    }

    public function show(Request $request, Child $child)
    {
        if ($e = $this->guard($request)) {
            return $e;
        }

        return ApiResponse::ok($this->view($request, $child, $this->book($request, $this->year($request))));
    }

    private function rules(): array
    {
        $r = [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'responsible_name' => ['nullable', 'string', 'max:150'],
            'responsible_relation' => ['nullable', 'string', 'max:60'],
            'choices' => ['nullable', 'array'],
            'changes' => ['nullable', 'string', 'max:400'],
            'comment' => ['nullable', 'string', 'max:800'],
            'message' => ['nullable', 'string', 'max:800'],
            'codes' => ['nullable', 'string', 'max:120', 'regex:/^[cmx0-9a-z.+\-|]*$/'],
        ];
        foreach (DarAlBerReport::CHOICES as $k => $list) {
            $r['choices.'.$k] = ['nullable', 'string', 'in:'.implode(',', array_keys($list))];
        }

        return $r;
    }

    public function save(Request $request, Child $child)
    {
        if ($e = $this->guard($request)) {
            return $e;
        }
        $data = $request->validate($this->rules());
        $r = OrphanReport::query()->firstOrNew(['child_id' => $child->id, 'year' => (int) $data['year']]);
        $choices = array_filter((array) ($data['choices'] ?? []), fn ($v) => $v !== null && $v !== '');
        $new = [
            'responsible_name' => trim((string) ($data['responsible_name'] ?? '')) ?: null,
            'responsible_relation' => trim((string) ($data['responsible_relation'] ?? '')) ?: null,
            'choices' => $choices ?: null,
            'changes' => trim((string) ($data['changes'] ?? '')) ?: null,
            'comment' => trim((string) ($data['comment'] ?? '')) ?: null,
            'message' => trim((string) ($data['message'] ?? '')) ?: null,
            'codes' => $data['codes'] ?? null,
        ];
        $changed = ! $r->exists || collect($new)->except('codes')->contains(fn ($v, $k) => $r->{$k} != $v);
        $r->fill($new + ['foundation_id' => $child->foundation_id, 'partner_id' => $child->partner_id, 'updated_by' => $request->user()->id]);
        if ($changed && $r->status === 'approved') {   // a changed report needs approving again
            $r->status = 'draft';
            $r->approved_by = null;
            $r->approved_at = null;
        }
        $r->status ??= 'draft';
        $r->save();
        Audit::record('report.saved', $request, 'child', $child->id, ['year' => $r->year]);

        return ApiResponse::updated($this->view($request, $child, $this->book($request, $r->year)));
    }

    /** Writes another text for one of the three boxes (not saved until Save). */
    public function write(Request $request, Child $child)
    {
        if ($e = $this->guard($request)) {
            return $e;
        }
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'], 'kind' => ['required', 'in:changes,comment,message'],
            'attempt' => ['nullable', 'integer', 'min:0', 'max:100000'], 'avoid' => ['nullable', 'string', 'max:800'],
        ] + array_intersect_key($this->rules(), array_flip(array_map(fn ($k) => 'choices.'.$k, array_keys(DarAlBerReport::CHOICES)))));
        $book = $this->book($request, (int) $data['year']);
        $tmp = new OrphanReport(['choices' => array_filter((array) ($request->input('choices') ?? []))]);
        $f = DarAlBerReport::facts($child, $tmp, $book->year, $book->settings);
        $texts = ReportTexts::forYear($book->year, $child->id);
        if (! empty($data['avoid'])) {
            $texts->take($data['kind'], $data['avoid']);
        }
        $from = (int) ($data['attempt'] ?? 0) + 1;
        [$text, $code, $used] = $texts->make($data['kind'], DarAlBerReport::phraseInput($f), $child->id, $book->year, $from);

        return ApiResponse::ok(['kind' => $data['kind'], 'text' => $text, 'code' => $code, 'attempt' => $used]);
    }

    public function approve(Request $request, Child $child)
    {
        if ($e = $this->guard($request)) {
            return $e;
        }
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100'], 'approved' => ['required', 'boolean']]);
        $book = $this->book($request, (int) $data['year']);
        $r = $book->report($child);
        if (! $r) {   // approving a report nobody saved yet: keep what was written for it
            $t = $book->textsFor($child);
            $r = OrphanReport::create(['foundation_id' => $child->foundation_id, 'partner_id' => $child->partner_id, 'child_id' => $child->id, 'year' => $book->year,
                'changes' => $t['changes'], 'comment' => $t['comment'], 'message' => $t['message'], 'codes' => $t['codes'], 'status' => 'draft', 'updated_by' => $request->user()->id]);
        }
        $r->update($data['approved'] ? ['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()] : ['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
        Audit::record($data['approved'] ? 'report.approved' : 'report.unapproved', $request, 'child', $child->id, ['year' => $r->year]);

        return ApiResponse::updated($this->view($request, $child, $this->book($request, $r->year)));
    }

    private function photo(Child $child): ?string
    {
        $p = $child->photos()->orderByDesc('id')->first();

        $bytes = $p && $p->path && Storage::disk('local')->exists($p->path) ? Storage::disk('local')->get($p->path) : null;

        return $bytes === null ? null : \App\Support\PrivateImages::cropToRatio($bytes, 3 / 4);   // older photos of another shape fill the form's 3:4 box
    }

    /**
     * "Dar Al Ber 2026 - 161186 - Amina Hassan - أمينة حسن.pdf": the year, the orphan number, then the English and the
     * Arabic name, so many reports saved together are easy to tell apart. Signs a file name cannot have are left out.
     */
    public static function fileName(Child $c, int $year): string
    {
        $clean = fn (?string $s) => trim(preg_replace('#\s+#u', ' ', preg_replace('#[\\\\/:*?"<>|\x00-\x1F\x7F\x{200E}\x{200F}\x{202A}-\x{202E}]+#u', ' ', (string) $s)));
        $en = mb_substr($clean($c->name_en), 0, 100);
        $ar = mb_substr($clean($c->name_ar), 0, 100);
        if ($ar === $en) {
            $ar = '';
        }
        $parts = array_filter(['Dar Al Ber '.$year, $clean((string) ($c->external_no ?: $c->ref)), $en, $ar], fn ($p) => $p !== '');

        return implode(' - ', $parts).'.pdf';
    }

    /** The same name in plain English letters, for programs that cannot show Arabic in a file name. */
    private static function plainName(string $name): string
    {
        $plain = trim(preg_replace('#\s+#', ' ', preg_replace('#[^\x20-\x7E]|[%/\\\\"]#', ' ', $name)));
        $plain = preg_replace('#(\s*-\s*)+\.pdf$#', '.pdf', $plain);

        return $plain !== '' && $plain !== '.pdf' ? $plain : 'report.pdf';
    }

    private function noForm(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::error('NO_FORM', 'The Dar Al Ber report form has not been added yet. The President adds it on the Partners page.', 409);
    }

    public function pdf(Request $request, Child $child)
    {
        if ($e = $this->guard($request)) {
            return $e;
        }
        $tpl = ReportTemplates::document((int) $request->user()->foundation_id, DarAlBerReport::PARTNER);
        if (! $tpl) {
            return $this->noForm();
        }
        $book = $this->book($request, $this->year($request));
        $pdf = DarAlBerReport::pdf($tpl, $book->facts($child), $this->photo($child));
        Audit::record('report.downloaded', $request, 'child', $child->id, ['year' => $book->year]);
        $name = self::fileName($child, $book->year);

        $disposition = \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('attachment', $name, self::plainName($name));

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => $disposition, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** The year at a glance, for downloading many at once. */
    public function index(Request $request)
    {
        if ($e = $this->guard($request)) {
            return $e;
        }
        $book = $this->book($request, $this->year($request));
        $rows = $book->children()->map(fn (Child $c) => ['id' => $c->id, 'ref' => $c->ref, 'name' => $c->name_en ?: $c->name_ar, 'status' => $book->report($c)?->status ?? 'new'])->values();

        return ApiResponse::ok([
            'year' => $book->year, 'form' => $this->form($request), 'per_part' => 20,
            'counts' => ['all' => $rows->count(), 'approved' => $rows->where('status', 'approved')->count(), 'draft' => $rows->where('status', 'draft')->count(), 'new' => $rows->where('status', 'new')->count()],
            'rows' => $rows,
        ]);
    }

    /** Many PDFs in one ZIP: approved ones (or everyone), in parts of up to 20 so a phone can save them. */
    public function zip(Request $request)
    {
        if ($e = $this->guard($request)) {
            return $e;
        }
        $data = $request->validate(['scope' => ['nullable', 'in:approved,all'], 'part' => ['nullable', 'integer', 'min:1', 'max:1000'], 'per' => ['nullable', 'integer', 'min:1', 'max:30'], 'ids' => ['nullable', 'array', 'max:500'], 'ids.*' => ['integer']]);
        $tpl = ReportTemplates::document((int) $request->user()->foundation_id, DarAlBerReport::PARTNER);
        if (! $tpl) {
            return $this->noForm();
        }
        if (! class_exists(\ZipArchive::class)) {
            return ApiResponse::error('NO_ZIP', 'This server cannot make ZIP files (PHP zip is off). Download the reports one by one from each profile.', 500);
        }
        @set_time_limit(300);
        $book = $this->book($request, $this->year($request));
        $scope = $data['scope'] ?? 'approved';
        $list = $book->children()->filter(fn (Child $c) => $scope === 'all' || $book->report($c)?->status === 'approved');
        if (! empty($data['ids'])) {
            $list = $list->whereIn('id', array_map('intval', $data['ids']));
        }
        $list = $list->values();
        $per = (int) ($data['per'] ?? 20);
        $part = (int) ($data['part'] ?? 1);
        $slice = $list->slice(($part - 1) * $per, $per)->values();
        if ($slice->isEmpty()) {
            return ApiResponse::error('NOTHING', $scope === 'approved' ? 'No approved reports for '.$book->year.' yet.' : 'No orphans to make reports for.', 404);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'rep');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $files = [];
        $used = [];
        foreach ($slice as $c) {
            $f = tempnam(sys_get_temp_dir(), 'pdf');
            file_put_contents($f, DarAlBerReport::pdf($tpl, $book->facts($c), $this->photo($c)));
            $files[] = $f;
            $name = self::fileName($c, $book->year);
            if (isset($used[$name])) {   // the same number and names twice: keep both
                $name = preg_replace('/\.pdf$/', ' ('.$c->ref.').pdf', $name);
            }
            $used[$name] = true;
            $zip->addFile($f, $name, 0, 0, \ZipArchive::FL_ENC_UTF_8);   // Arabic names marked as UTF-8 so they open right
            $zip->setCompressionName($name, \ZipArchive::CM_STORE);   // PDFs are compressed already
        }
        $zip->close();
        foreach ($files as $f) {
            @unlink($f);
        }
        $parts = (int) ceil($list->count() / $per);
        Audit::record('report.batch_downloaded', $request, null, null, ['year' => $book->year, 'scope' => $scope, 'part' => $part, 'count' => $slice->count()]);
        $name = 'Dar Al Ber reports '.$book->year.($parts > 1 ? ' part '.$part.' of '.$parts : '').'.zip';

        return response()->download($tmp, $name, ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store', 'X-Parts' => (string) $parts, 'X-Count' => (string) $slice->count(), 'Access-Control-Expose-Headers' => 'X-Parts, X-Count'])->deleteFileAfterSend(true);
    }
}
