<?php

namespace App\Http\Controllers\Api;

use App\Core\Audit\Auditor;
use App\Core\Programs\ProgramAccess;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Programs\ProgramModules;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Programs: what a foundation does (Aytam, Relief, Education...). A program is a generic record; the module behind it
 * (if one exists) supplies its own data, screens and roles. Creating "another kind of program" needs no code here.
 */
class ProgramController extends Controller
{
    public function __construct(private ProgramAccess $access, private Auditor $auditor) {}

    /** What can be created, and which categories already have a working module. */
    public function types(): JsonResponse
    {
        return ApiResponse::ok(collect(ProgramModules::CATEGORIES)->map(function ($label, $key) {
            $module = ProgramModules::moduleForCategory($key);

            return ['category' => $key, 'label' => $label, 'module' => $module?->key(), 'module_label' => $module?->label(), 'available' => $module !== null];
        })->values());
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(Program::STATUSES)], 'q' => ['nullable', 'string', 'max:100']]);

        $programs = $this->access->visibleTo($request->user())
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', Program::ARCHIVED))
            ->when($request->q, fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->orderBy('name')->get();

        return ApiResponse::ok($programs->map(fn (Program $p) => $this->present($p))->all());
    }

    public function show(Request $request, Program $program): JsonResponse
    {
        abort_unless($this->access->canView($request->user(), $program), 404);

        return ApiResponse::ok($this->present($program, detail: true) + [
            'can' => ['update' => $this->access->canManage($request->user(), $program), 'configure' => $this->access->canConfigure($request->user(), $program),
                'activate' => $request->user()->hasPermission('programs.activate')],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(ProgramModules::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $module = ProgramModules::moduleForCategory($data['category']);

        $program = new Program;
        $program->forceFill([
            'name' => $data['name'], 'slug' => $this->uniqueSlug($data['name']), 'description' => $data['description'] ?? null,
            'category' => $data['category'], 'module' => $module?->key(), 'status' => Program::DRAFT,
            'start_date' => $data['start_date'] ?? null, 'end_date' => $data['end_date'] ?? null,
            'config' => $module?->defaultConfig() ?: null,
        ])->save();

        return ApiResponse::created($this->present($program->refresh(), detail: true));
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        if ($program->status === Program::ARCHIVED) {
            return ApiResponse::error('PROGRAM_ARCHIVED', 'An archived program cannot be edited.', 409);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'config' => ['sometimes', 'array'],
        ]);
        if (isset($data['config'])) {
            $rules = $program->moduleDefinition()?->configRules() ?? [];
            $unknown = array_diff(array_keys($data['config']), array_map(fn ($k) => explode('.', $k)[0], array_keys($rules)));
            abort_if($unknown !== [], 422, 'Unknown configuration: '.implode(', ', $unknown));
            Validator::make($data['config'], $rules)->validate();
            $data['config'] = array_replace($program->config ?? [], $data['config']);
        }
        $program->forceFill($data)->save();

        return ApiResponse::ok($this->present($program->refresh(), detail: true));
    }

    /** draft/inactive -> active, active -> inactive, anything -> archived. Needs programs.activate. */
    public function status(Request $request, Program $program): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(Program::STATUSES)]]);
        $to = $data['status'];
        if ($to === $program->status) {
            return ApiResponse::ok($this->present($program, detail: true));
        }
        if (! in_array($to, Program::TRANSITIONS[$program->status] ?? [], true)) {
            return ApiResponse::error('INVALID_TRANSITION', "A {$program->status} program cannot become {$to}.", 422);
        }
        $from = $program->status;
        $program->forceFill(['status' => $to])->save();
        $this->auditor->record('program.'.$to, "Program \"{$program->name}\" changed from {$from} to {$to}", 'programs', $program->getKey(), ['status' => $from], ['status' => $to]);

        return ApiResponse::ok($this->present($program->refresh(), detail: true));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'program';
        $slug = $base;
        for ($i = 2; Program::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function present(Program $p, bool $detail = false): array
    {
        $out = [
            'id' => $p->getKey(), 'name' => $p->name, 'slug' => $p->slug, 'category' => $p->category,
            'category_label' => ProgramModules::CATEGORIES[$p->category] ?? $p->category,
            'module' => $p->module, 'module_ready' => $p->moduleDefinition() !== null, 'status' => $p->status,
            'description' => $p->description, 'start_date' => $p->start_date?->toDateString(), 'end_date' => $p->end_date?->toDateString(),
            'version' => $p->version, 'created_at' => $p->created_at?->toIso8601String(),
        ];

        return $detail ? $out + ['config' => $p->config ?? (object) [], 'transitions' => Program::TRANSITIONS[$p->status] ?? []] : $out;
    }
}
