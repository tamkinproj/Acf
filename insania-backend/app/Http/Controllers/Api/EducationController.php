<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Education\Contract;
use App\Models\Education\Curriculum;
use App\Models\Education\Institution;
use App\Models\Education\Salary;
use App\Models\Education\Teacher;
use App\Models\Education\Unit;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\PermissionCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Education department: Alimni teachers (contracts of 6 months that can be renewed, monthly salary), the institutions they teach at
 * (the foundation's madrasa and partner markaz) and the curriculum from Dar Al Ber. Salary amounts are only sent to people who hold education.salary.
 */
class EducationController extends Controller
{
    private function salaryOk(Request $r): bool
    {
        return PermissionCatalog::allows($r->user(), 'education.salary');
    }

    private static function nz(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return $v === '' ? null : $v;
    }

    /* ------------------------------------------------------------ summary */
    public function summary(Request $request)
    {
        $teachers = Teacher::with('contracts')->get();
        $count = ['active' => 0, 'ending_soon' => 0, 'expired' => 0, 'none' => 0];
        $ending = [];
        foreach ($teachers as $t) {
            if ($t->status !== 'active') {
                continue;
            }
            $c = $t->current();
            $s = $c ? $c->state() : 'none';
            $count[$s]++;
            if ($c && in_array($s, ['ending_soon', 'expired'], true)) {
                $ending[] = ['id' => $t->id, 'ref' => $t->ref, 'name_en' => $t->name_en, 'name_ar' => $t->name_ar, 'state' => $s, 'end_date' => $c->end_date->toDateString(), 'days_left' => $c->daysLeft()];
            }
        }
        usort($ending, fn ($a, $b) => $a['days_left'] <=> $b['days_left']);

        return ApiResponse::ok([
            'teachers' => $teachers->where('status', 'active')->count(), 'contracts' => $count, 'attention' => array_slice($ending, 0, 8),
            'madrasa' => Institution::where('kind', 'madrasa')->where('status', 'active')->count(), 'markaz' => Institution::where('kind', 'markaz')->where('status', 'active')->count(),
            'curricula' => Curriculum::where('status', 'active')->count(),
        ]);
    }

    /* ------------------------------------------------------------ teachers */
    private function teacherRow(Teacher $t, Request $r): array
    {
        $c = $t->current();

        return [
            'id' => $t->id, 'ref' => $t->ref, 'name_en' => $t->name_en, 'name_ar' => $t->name_ar, 'status' => $t->status, 'phone' => $t->phone,
            'institution' => $t->institution?->name_en, 'institution_id' => $t->institution_id,
            'contract_state' => $c ? $c->state() : 'none', 'end_date' => $c?->end_date?->toDateString(), 'days_left' => $c ? $c->daysLeft() : null,
        ];
    }

    public function teachers(Request $request)
    {
        $rows = Teacher::with(['institution', 'contracts'])->orderBy('ref')->get()->map(fn (Teacher $t) => $this->teacherRow($t, $request));

        return ApiResponse::collection($rows);
    }

    private function teacherRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name_en' => [$req, 'string', 'max:150'], 'name_ar' => ['nullable', 'string', 'max:150'], 'gender' => ['nullable', 'in:m,f'],
            'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:150'], 'city' => ['nullable', 'string', 'max:100'],
            'qualification' => ['nullable', 'string', 'max:150'], 'specialization' => ['nullable', 'string', 'max:150'], 'hired_on' => ['nullable', 'date'],
            'institution_id' => ['nullable', 'integer'], 'curriculum_id' => ['nullable', 'integer'], 'status' => ['sometimes', 'in:active,inactive'], 'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function checkLinks(array $data): ?\Illuminate\Http\JsonResponse
    {
        if (! empty($data['institution_id']) && ! Institution::whereKey($data['institution_id'])->exists()) {
            return ApiResponse::error('VALIDATION_ERROR', 'Choose an institution from the list.', 422, ['institution_id' => ['Choose an institution from the list.']]);
        }
        if (! empty($data['curriculum_id']) && ! Curriculum::whereKey($data['curriculum_id'])->exists()) {
            return ApiResponse::error('VALIDATION_ERROR', 'Choose a curriculum from the list.', 422, ['curriculum_id' => ['Choose a curriculum from the list.']]);
        }

        return null;
    }

    public function storeTeacher(Request $request)
    {
        $data = $request->validate($this->teacherRules(true) + ['monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:1000000'], 'months' => ['nullable', 'integer', 'min:1', 'max:24'], 'start_date' => ['nullable', 'date']]);
        if ($bad = $this->checkLinks($data)) {
            return $bad;
        }
        $fid = $request->user()->foundation_id;
        $t = DB::transaction(function () use ($data, $fid, $request) {
            $cols = collect($data)->only(array_keys($this->teacherRules(true)))->all();
            $t = Teacher::create($cols + ['foundation_id' => $fid, 'ref' => 'TMP-'.\Illuminate\Support\Str::random(10)]);
            $t->update(['ref' => 'ALM-'.str_pad((string) $t->id, 4, '0', STR_PAD_LEFT)]);
            if (isset($data['monthly_salary']) || isset($data['start_date'])) {      // a first contract can be made together with the teacher
                $this->makeContract($t, $data['start_date'] ?? null, (int) ($data['months'] ?? 6), $this->salaryOk($request) ? (float) ($data['monthly_salary'] ?? 0) : 0.0);
            }

            return $t;
        });
        Audit::record('edu.teacher_created', $request, 'edu_teacher', $t->id);

        return ApiResponse::created($this->teacherShow($t->fresh(), $request));
    }

    public function teacher(Request $request, Teacher $teacher)
    {
        return ApiResponse::ok($this->teacherShow($teacher, $request));
    }

    private function teacherShow(Teacher $t, Request $r): array
    {
        $t->load(['institution', 'curriculum.units', 'contracts']);
        $sal = $this->salaryOk($r);
        $c = $t->current();
        $done = DB::table('edu_progress')->where('teacher_id', $t->id)->pluck('done_on', 'unit_id');
        $out = $this->teacherRow($t, $r) + [
            'gender' => $t->gender, 'email' => $t->email, 'city' => $t->city, 'qualification' => $t->qualification, 'specialization' => $t->specialization,
            'hired_on' => $t->hired_on?->toDateString(), 'notes' => $t->notes, 'curriculum_id' => $t->curriculum_id, 'created_at' => $t->created_at?->toDateString(),
            'contract' => $c ? $this->contractOut($c, $sal) : null,
            'contracts' => $t->contracts->map(fn (Contract $x) => $this->contractOut($x, $sal))->values(),
            'curriculum' => $t->curriculum ? ['id' => $t->curriculum->id, 'title_en' => $t->curriculum->title_en, 'title_ar' => $t->curriculum->title_ar, 'source' => $t->curriculum->source,
                'units' => $t->curriculum->units->map(fn (Unit $u) => ['id' => $u->id, 'title_en' => $u->title_en, 'title_ar' => $u->title_ar, 'done' => $done->has($u->id), 'done_on' => $done->get($u->id)])->values()] : null,
            'can_salary' => $sal,
        ];
        if ($sal) {
            $out['salaries'] = $t->salaries()->limit(12)->get()->map(fn (Salary $s) => $this->salaryOut($s))->values();
        }

        return $out;
    }

    private function contractOut(Contract $c, bool $withMoney): array
    {
        return ['id' => $c->id, 'start_date' => $c->start_date->toDateString(), 'end_date' => $c->end_date->toDateString(), 'state' => $c->state(), 'days_left' => $c->daysLeft(), 'note' => $c->note]
            + ($withMoney ? ['monthly_salary' => (float) $c->monthly_salary] : []);
    }

    private function salaryOut(Salary $s): array
    {
        return ['id' => $s->id, 'month' => $s->month, 'amount' => (float) $s->amount, 'method' => $s->method, 'status' => $s->status, 'paid_at' => $s->paid_at?->toIso8601String()];
    }

    public function updateTeacher(Request $request, Teacher $teacher)
    {
        $data = $request->validate($this->teacherRules(false));
        if ($bad = $this->checkLinks($data)) {
            return $bad;
        }
        $teacher->fill(array_map(fn ($v) => is_string($v) ? self::nz($v) : $v, $data) + [])->save();
        Audit::record('edu.teacher_updated', $request, 'edu_teacher', $teacher->id, ['fields' => array_keys($data)]);

        return ApiResponse::updated($this->teacherShow($teacher->fresh(), $request));
    }

    /* ------------------------------------------------------------ contracts */
    private function makeContract(Teacher $t, ?string $start, int $months, float $salary): Contract
    {
        $last = $t->contracts()->first();
        $from = $start ? Carbon::parse($start) : ($last && $last->end_date->gte(now()->startOfDay()) ? $last->end_date->copy()->addDay() : now()->startOfDay());
        $end = $from->copy()->addMonthsNoOverflow($months)->subDay();
        Contract::where('teacher_id', $t->id)->where('status', 'active')->update(['status' => 'ended']);

        return Contract::create(['foundation_id' => $t->foundation_id, 'teacher_id' => $t->id, 'start_date' => $from->toDateString(), 'end_date' => $end->toDateString(), 'monthly_salary' => $salary, 'status' => 'active']);
    }

    /** POST edu/teachers/{id}/contracts - a new contract, or a renewal that follows the last one. Six months unless another length is chosen. */
    public function storeContract(Request $request, Teacher $teacher)
    {
        $data = $request->validate(['start_date' => ['nullable', 'date'], 'months' => ['nullable', 'integer', 'min:1', 'max:24'], 'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:1000000'], 'note' => ['nullable', 'string', 'max:300']]);
        $prev = $teacher->contracts()->first();
        $salary = $this->salaryOk($request) && isset($data['monthly_salary']) ? (float) $data['monthly_salary'] : (float) ($prev?->monthly_salary ?? 0);
        $c = $this->makeContract($teacher, $data['start_date'] ?? null, (int) ($data['months'] ?? 6), $salary);
        if (! empty($data['note'])) {
            $c->update(['note' => $data['note']]);
        }
        if ($teacher->status !== 'active') {
            $teacher->update(['status' => 'active']);
        }
        Audit::record($prev ? 'edu.contract_renewed' : 'edu.contract_created', $request, 'edu_teacher', $teacher->id, ['until' => $c->end_date->toDateString()]);

        return ApiResponse::created($this->teacherShow($teacher->fresh(), $request));
    }

    public function updateContract(Request $request, Contract $contract)
    {
        $data = $request->validate(['end_date' => ['sometimes', 'date'], 'monthly_salary' => ['sometimes', 'numeric', 'min:0', 'max:1000000'], 'note' => ['sometimes', 'nullable', 'string', 'max:300']]);
        if (isset($data['monthly_salary']) && ! $this->salaryOk($request)) {
            return ApiResponse::forbidden();
        }
        if (isset($data['end_date']) && Carbon::parse($data['end_date'])->lt($contract->start_date)) {
            return ApiResponse::error('VALIDATION_ERROR', 'The end date must be after the start date.', 422, ['end_date' => ['The end date must be after the start date.']]);
        }
        $contract->update($data);
        Audit::record('edu.contract_updated', $request, 'edu_teacher', $contract->teacher_id, ['fields' => array_keys($data)]);

        return ApiResponse::updated($this->teacherShow(Teacher::findOrFail($contract->teacher_id), $request));
    }

    /* ------------------------------------------------------------ salaries */
    /** GET edu/salaries?month=YYYY-MM - everyone's line for the month, with the totals. */
    public function salaries(Request $request)
    {
        $month = $this->month($request);
        $rows = Salary::with('teacher')->where('month', $month)->get()->sortBy(fn ($s) => $s->teacher?->ref)->values();
        $paid = $rows->where('status', 'paid');

        return ApiResponse::ok([
            'month' => $month,
            'rows' => $rows->map(fn (Salary $s) => $this->salaryOut($s) + ['teacher_id' => $s->teacher_id, 'ref' => $s->teacher?->ref, 'name_en' => $s->teacher?->name_en, 'name_ar' => $s->teacher?->name_ar])->values(),
            'totals' => ['paid_count' => $paid->count(), 'pending_count' => $rows->where('status', 'pending')->count(), 'paid_total' => (float) $paid->sum('amount'), 'due_total' => (float) $rows->where('status', 'pending')->sum('amount')],
        ]);
    }

    private function month(Request $request): string
    {
        $m = (string) $request->input('month', $request->query('month', now()->format('Y-m')));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now()->format('Y-m');
    }

    /** POST edu/salaries/generate {month} - makes a pending line for every active teacher whose contract covers that month. */
    public function generateSalaries(Request $request)
    {
        $month = $this->month($request);
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $made = 0;
        foreach (Teacher::where('status', 'active')->with('contracts')->get() as $t) {
            $c = $t->contracts->first(fn (Contract $x) => $x->start_date->lte($to) && $x->end_date->gte($from));
            if (! $c || Salary::where('teacher_id', $t->id)->where('month', $month)->exists()) {
                continue;
            }
            Salary::create(['foundation_id' => $t->foundation_id, 'teacher_id' => $t->id, 'contract_id' => $c->id, 'month' => $month, 'amount' => $c->monthly_salary, 'method' => 'cash', 'status' => 'pending']);
            $made++;
        }
        Audit::record('edu.salaries_generated', $request, null, null, ['month' => $month, 'made' => $made]);

        return ApiResponse::ok(['month' => $month, 'created' => $made]);
    }

    /** PUT edu/teachers/{id}/salaries/{month} - mark a month paid (or not), change the amount or how it was paid. */
    public function setSalary(Request $request, Teacher $teacher, string $month)
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return ApiResponse::error('VALIDATION_ERROR', 'Use a month like 2026-12.', 422);
        }
        $data = $request->validate(['status' => ['sometimes', 'in:pending,paid'], 'amount' => ['sometimes', 'numeric', 'min:0', 'max:1000000'], 'method' => ['sometimes', 'in:cash,bank']]);
        $s = Salary::where('teacher_id', $teacher->id)->where('month', $month)->first();
        if (! $s) {
            $from = Carbon::parse($month.'-01');
            $c = $teacher->contracts()->get()->first(fn (Contract $x) => $x->start_date->lte($from->copy()->endOfMonth()) && $x->end_date->gte($from));
            $s = Salary::create(['foundation_id' => $teacher->foundation_id, 'teacher_id' => $teacher->id, 'contract_id' => $c?->id, 'month' => $month, 'amount' => $data['amount'] ?? ($c?->monthly_salary ?? 0), 'status' => 'pending', 'method' => 'cash']);
        }
        if (isset($data['status'])) {
            $s->status = $data['status'];
            $s->paid_at = $data['status'] === 'paid' ? now() : null;
            $s->recorded_by = $request->user()->id;
        }
        foreach (['amount', 'method'] as $k) {
            if (isset($data[$k])) {
                $s->{$k} = $data[$k];
            }
        }
        $s->save();
        Audit::record('edu.salary_recorded', $request, 'edu_teacher', $teacher->id, ['month' => $month, 'status' => $s->status]);

        return ApiResponse::updated($this->salaryOut($s));
    }

    /** PUT edu/teachers/{id}/progress/{unit} - tick or untick a unit of the teacher's curriculum. */
    public function setProgress(Request $request, Teacher $teacher, int $unit)
    {
        $data = $request->validate(['done' => ['required', 'boolean']]);
        $u = Unit::where('curriculum_id', $teacher->curriculum_id)->find($unit);
        if (! $u) {
            return ApiResponse::error('VALIDATION_ERROR', 'This unit is not in the teacher\'s curriculum.', 422);
        }
        if ($data['done']) {
            DB::table('edu_progress')->updateOrInsert(['teacher_id' => $teacher->id, 'unit_id' => $u->id], ['done_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('edu_progress')->where('teacher_id', $teacher->id)->where('unit_id', $u->id)->delete();
        }

        return ApiResponse::ok(['unit' => $u->id, 'done' => (bool) $data['done']]);
    }

    /* ------------------------------------------------------------ institutions */
    private function instRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'kind' => [$req, 'in:madrasa,markaz'], 'partnership' => ['nullable', 'in:own,partner'], 'name_en' => [$req, 'string', 'max:150'], 'name_ar' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:300'], 'contact_name' => ['nullable', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'],
            'since' => ['nullable', 'date'], 'status' => ['sometimes', 'in:active,ended'], 'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function instOut(Institution $i, bool $withTeachers = false): array
    {
        $out = ['id' => $i->id, 'kind' => $i->kind, 'partnership' => $i->partnership, 'name_en' => $i->name_en, 'name_ar' => $i->name_ar, 'city' => $i->city, 'address' => $i->address, 'contact_name' => $i->contact_name,
            'phone' => $i->phone, 'since' => $i->since?->toDateString(), 'status' => $i->status, 'notes' => $i->notes, 'teachers_count' => $i->teachers_count ?? $i->teachers()->count()];
        if ($withTeachers) {
            $out['teachers'] = $i->teachers()->with('contracts')->orderBy('ref')->get()->map(fn (Teacher $t) => ['id' => $t->id, 'ref' => $t->ref, 'name_en' => $t->name_en, 'name_ar' => $t->name_ar, 'status' => $t->status, 'contract_state' => $t->current() ? $t->current()->state() : 'none'])->values();
        }

        return $out;
    }

    public function institutions()
    {
        return ApiResponse::collection(Institution::withCount('teachers')->orderBy('kind')->orderBy('name_en')->get()->map(fn ($i) => $this->instOut($i)));
    }

    public function storeInstitution(Request $request)
    {
        $data = $request->validate($this->instRules(true));
        $i = Institution::create(array_map(fn ($v) => is_string($v) ? self::nz($v) : $v, $data) + ['foundation_id' => $request->user()->foundation_id]);
        Audit::record('edu.institution_created', $request, 'edu_institution', $i->id);

        return ApiResponse::created($this->instOut($i->fresh(), true));
    }

    public function institution(Institution $institution)
    {
        return ApiResponse::ok($this->instOut($institution, true));
    }

    public function updateInstitution(Request $request, Institution $institution)
    {
        $data = $request->validate($this->instRules(false));
        $institution->fill(array_map(fn ($v) => is_string($v) ? self::nz($v) : $v, $data))->save();
        Audit::record('edu.institution_updated', $request, 'edu_institution', $institution->id, ['fields' => array_keys($data)]);

        return ApiResponse::updated($this->instOut($institution->fresh(), true));
    }

    /* ------------------------------------------------------------ curriculum */
    private function curOut(Curriculum $c, bool $withUnits = false): array
    {
        $out = ['id' => $c->id, 'title_en' => $c->title_en, 'title_ar' => $c->title_ar, 'source' => $c->source, 'level' => $c->level, 'description' => $c->description, 'status' => $c->status,
            'units_count' => $c->units_count ?? $c->units()->count(), 'teachers_count' => $c->teachers_count ?? $c->teachers()->count()];
        if ($withUnits) {
            $out['units'] = $c->units()->get()->map(fn (Unit $u) => ['id' => $u->id, 'position' => $u->position, 'title_en' => $u->title_en, 'title_ar' => $u->title_ar, 'description' => $u->description])->values();
            $out['teachers'] = $c->teachers()->orderBy('ref')->get(['id', 'ref', 'name_en', 'name_ar'])->values();
        }

        return $out;
    }

    public function curricula()
    {
        return ApiResponse::collection(Curriculum::withCount(['units', 'teachers'])->orderBy('title_en')->get()->map(fn ($c) => $this->curOut($c)));
    }

    private function curRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return ['title_en' => [$req, 'string', 'max:150'], 'title_ar' => ['nullable', 'string', 'max:150'], 'source' => ['nullable', 'string', 'max:100'], 'level' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:3000'], 'status' => ['sometimes', 'in:active,archived']];
    }

    public function storeCurriculum(Request $request)
    {
        $data = $request->validate($this->curRules(true) + ['units' => ['nullable', 'array', 'max:200'], 'units.*' => ['string', 'max:200']]);
        $c = DB::transaction(function () use ($data, $request) {
            $c = Curriculum::create(array_map(fn ($v) => is_string($v) ? self::nz($v) : $v, collect($data)->except('units')->all()) + ['foundation_id' => $request->user()->foundation_id, 'source' => self::nz($data['source'] ?? null) ?? 'Dar Al Ber']);
            foreach (array_values(array_filter(array_map('trim', $data['units'] ?? []))) as $i => $title) {
                Unit::create(['foundation_id' => $c->foundation_id, 'curriculum_id' => $c->id, 'position' => $i + 1, 'title_en' => $title]);
            }

            return $c;
        });
        Audit::record('edu.curriculum_created', $request, 'edu_curriculum', $c->id);

        return ApiResponse::created($this->curOut($c->fresh(), true));
    }

    public function curriculum(Curriculum $curriculum)
    {
        return ApiResponse::ok($this->curOut($curriculum, true));
    }

    public function updateCurriculum(Request $request, Curriculum $curriculum)
    {
        $data = $request->validate($this->curRules(false));
        $curriculum->fill(array_map(fn ($v) => is_string($v) ? self::nz($v) : $v, $data))->save();
        Audit::record('edu.curriculum_updated', $request, 'edu_curriculum', $curriculum->id, ['fields' => array_keys($data)]);

        return ApiResponse::updated($this->curOut($curriculum->fresh(), true));
    }

    public function storeUnit(Request $request, Curriculum $curriculum)
    {
        $data = $request->validate(['title_en' => ['required', 'string', 'max:200'], 'title_ar' => ['nullable', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:2000']]);
        Unit::create($data + ['foundation_id' => $curriculum->foundation_id, 'curriculum_id' => $curriculum->id, 'position' => (int) $curriculum->units()->max('position') + 1]);
        Audit::record('edu.unit_added', $request, 'edu_curriculum', $curriculum->id);

        return ApiResponse::created($this->curOut($curriculum->fresh(), true));
    }

    public function updateUnit(Request $request, Unit $unit)
    {
        $data = $request->validate(['title_en' => ['sometimes', 'string', 'max:200'], 'title_ar' => ['sometimes', 'nullable', 'string', 'max:200'], 'description' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        $unit->fill(array_map(fn ($v) => is_string($v) ? self::nz($v) : $v, $data))->save();

        return ApiResponse::updated($this->curOut(Curriculum::findOrFail($unit->curriculum_id), true));
    }

    public function deleteUnit(Request $request, Unit $unit)
    {
        $cid = $unit->curriculum_id;
        $unit->delete();
        Audit::record('edu.unit_removed', $request, 'edu_curriculum', $cid);

        return ApiResponse::ok($this->curOut(Curriculum::findOrFail($cid), true));
    }
}
