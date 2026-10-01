<?php

namespace App\Http\Controllers\Api\Aytam;

use App\Http\Controllers\Controller;
use App\Models\Aytam;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Program;
use App\Modules\Aytam\Import\ImportMapper;
use App\Modules\Aytam\Import\ImportService;
use App\Support\ApiResponse;
use App\Sync\RejectChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Importing existing data from a spreadsheet: every step is a separate request, so people can stop, look and decide. */
class ImportController extends Controller
{
    public function __construct(private ImportService $imports) {}

    public function index(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $batches = ImportBatch::query()->where('program_id', $program->getKey())->orderByDesc('created_at')->limit(50)->get();

        return ApiResponse::ok($batches->map(fn ($b) => $this->brief($b))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $request->validate(['file' => ['required', 'file']]);

        try {
            $batch = $this->imports->stage($program, $request->file('file'));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error('INVALID_FILE', $e->getMessage(), 422, ['file' => [$e->getMessage()]]);
        }

        return ApiResponse::created($this->detail($batch, withSample: true));
    }

    public function show(Request $request, string $program, string $batch): JsonResponse
    {
        return ApiResponse::ok($this->detail($this->find($request, $batch), withSample: true));
    }

    public function rows(Request $request, string $program, string $batch): JsonResponse
    {
        $record = $this->find($request, $batch);
        $request->validate(['status' => ['nullable', Rule::in(['pending', 'valid', 'invalid', 'duplicate', 'imported', 'skipped'])], 'undecided' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);

        $page = ImportRow::query()->where('batch_id', $record->getKey())
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('undecided'), fn ($q) => $q->where('status', ImportRow::DUPLICATE)->whereNull('decision'))
            ->orderBy('row_number')->paginate((int) $request->input('per_page', 25));

        return ApiResponse::ok($page->getCollection()->map(fn ($r) => $this->presentRow($r, $record))->all(), [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]);
    }

    public function mapping(Request $request, string $program, string $batch): JsonResponse
    {
        $record = $this->find($request, $batch);
        $data = $request->validate(['mapping' => ['required', 'array'], 'date_format' => ['required', Rule::in(array_keys(ImportMapper::DATE_FORMATS))]]);

        return $this->guard(fn () => ApiResponse::ok($this->detail($this->imports->map($record, $data['mapping'], $data['date_format']))));
    }

    public function validateBatch(Request $request, string $program, string $batch): JsonResponse
    {
        $record = $this->find($request, $batch);

        return $this->guard(function () use ($record, $request) {
            $summary = $this->imports->validate($record, $request->attributes->get('program'));

            return ApiResponse::ok($summary + ['batch' => $this->detail($record->refresh())]);
        });
    }

    public function decide(Request $request, string $program, string $batch, string $row): JsonResponse
    {
        $record = $this->find($request, $batch);
        $model = ImportRow::query()->where('batch_id', $record->getKey())->findOrFail($row);
        $data = $request->validate(['decision' => ['present', 'nullable', Rule::in(ImportService::DECISIONS)], 'existing_aytam_id' => ['nullable', 'uuid']]);

        return $this->guard(fn () => ApiResponse::ok($this->presentRow($this->imports->decide($model, $data['decision'], $data['existing_aytam_id'] ?? null, $request->attributes->get('program')), $record)));
    }

    public function decideAll(Request $request, string $program, string $batch): JsonResponse
    {
        $record = $this->find($request, $batch);
        $data = $request->validate(['decision' => ['required', Rule::in(['create_new', 'skip'])]]);

        return $this->guard(fn () => ApiResponse::ok(['updated' => $this->imports->decideAll($record, $data['decision'])]));
    }

    public function commit(Request $request, string $program, string $batch): JsonResponse
    {
        $record = $this->find($request, $batch);
        $data = $request->validate(['status' => ['nullable', Rule::in([Aytam::DRAFT, Aytam::APPROVED, Aytam::ACTIVE])], 'limit' => ['nullable', 'integer', 'between:1,500']]);

        return $this->guard(fn () => ApiResponse::ok($this->imports->commit($record, $request->attributes->get('program'), $request->user(), $data['status'] ?? Aytam::ACTIVE, $data['limit'] ?? 100) + ['batch' => $this->detail($record->refresh())]));
    }

    public function destroy(Request $request, string $program, string $batch): JsonResponse
    {
        $record = $this->find($request, $batch);

        return $this->guard(function () use ($record) {
            $this->imports->cancel($record);

            return ApiResponse::ok(null);
        });
    }

    private function guard(\Closure $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (RejectChange $e) {
            return ApiResponse::error(strtoupper($e->reason), $e->getMessage(), 422);
        }
    }

    private function find(Request $request, string $id): ImportBatch
    {
        /** @var Program $program */
        $program = $request->attributes->get('program');

        return ImportBatch::query()->where('program_id', $program->getKey())->findOrFail($id);
    }

    private function brief(ImportBatch $b): array
    {
        return ['id' => $b->getKey(), 'file_name' => $b->original_name, 'file_type' => $b->file_type, 'status' => $b->status, 'row_count' => $b->row_count,
            'summary' => $b->summary, 'created_at' => $b->created_at?->toIso8601String(), 'imported_at' => $b->imported_at?->toIso8601String()];
    }

    private function detail(ImportBatch $b, bool $withSample = false): array
    {
        $out = $this->brief($b) + ['headers' => $b->headers, 'mapping' => (object) ($b->mapping ?? []), 'date_format' => $b->summary['date_format'] ?? null];
        if ($withSample) {
            $out += [
                'sample' => ImportRow::query()->where('batch_id', $b->getKey())->orderBy('row_number')->limit(5)->get()->map(fn ($r) => $r->raw)->all(),
                'suggested_mapping' => (object) ($b->summary['suggested_mapping'] ?? []),
                'targets' => array_values(ImportMapper::targets()), 'date_formats' => ImportMapper::DATE_FORMATS,
            ];
        }

        return $out;
    }

    private function presentRow(ImportRow $r, ImportBatch $b): array
    {
        return [
            'id' => $r->getKey(), 'row_number' => $r->row_number, 'status' => $r->status, 'decision' => $r->decision, 'existing_aytam_id' => $r->existing_aytam_id,
            'aytam_id' => $r->aytam_id, 'cells' => $r->raw, 'errors' => $r->errors ?? (object) [], 'matches' => $r->matches ?? [],
            'preview' => $r->data['aytam'] ?? null,
        ];
    }
}
