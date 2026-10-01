<?php

namespace App\Http\Controllers\Api\Aytam;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Program;
use App\Models\Registration;
use App\Modules\Aytam\AytamRules;
use App\Modules\Aytam\DuplicateDetector;
use App\Modules\Aytam\Registration\DuplicatesFound;
use App\Modules\Aytam\Registration\FieldTypes;
use App\Modules\Aytam\Registration\RegistrationMapper;
use App\Modules\Aytam\Registration\RegistrationService;
use App\Support\ApiResponse;
use App\Sync\RejectChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The reviewer's side: see what was submitted, what is missing, who it might duplicate - then approve or send back. */
class RegistrationReviewController extends Controller
{
    public function __construct(private RegistrationService $service, private RegistrationMapper $mapper, private DuplicateDetector $duplicates) {}

    public function index(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $request->validate(['status' => ['nullable', Rule::in(Registration::STATUSES)], 'q' => ['nullable', 'string', 'max:100'], 'form_id' => ['nullable', 'uuid'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);

        $counts = Registration::query()->where('program_id', $program->getKey())->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $page = Registration::query()->where('program_id', $program->getKey())->with('form:id,title')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->form_id, fn ($q, $v) => $q->where('form_id', $v))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$t}%")->orWhere('applicant_name', 'like', "%{$t}%")))
            ->orderByRaw("case status when 'pending_review' then 0 when 'needs_correction' then 1 else 2 end")->orderByDesc('last_submitted_at')
            ->paginate((int) $request->input('per_page', 25));

        return ApiResponse::ok($page->getCollection()->map(fn (Registration $r) => $this->brief($r))->all(), [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
            'counts' => ['pending_review' => (int) ($counts['pending_review'] ?? 0), 'needs_correction' => (int) ($counts['needs_correction'] ?? 0), 'approved' => (int) ($counts['approved'] ?? 0)],
        ]);
    }

    public function show(Request $request, string $program, string $registration): JsonResponse
    {
        /** @var Program $prog */
        $prog = $request->attributes->get('program');
        $reg = $this->find($prog, $registration);
        $schema = $reg->formVersion->schema;
        $docs = Document::query()->where('registration_id', $reg->getKey())->where('is_current', true)->get()->keyBy('id');
        $canonical = $this->mapper->toCanonical($schema, $reg->answers);

        $problems = [];
        $pending = $reg->status === Registration::PENDING_REVIEW;
        if ($pending) {
            $rules = collect(AytamRules::rules($prog, true))->except(['family_id', 'guardian_id', 'legacy_ref'])->all();
            $problems = Validator::make($canonical['aytam'], $rules)->errors()->toArray();
        }
        $required = $prog->config['required_documents'] ?? [];
        $have = $docs->where('verification_status', '!=', Document::REJECTED)->pluck('type')->all();

        return ApiResponse::ok($this->brief($reg) + [
            'applicant' => ['name' => $reg->applicant_name, 'email' => $reg->applicant_email, 'phone' => $reg->applicant_phone],
            'review_note' => $reg->review_note, 'duplicate_decision' => $reg->duplicate_decision,
            'sections' => collect($schema['sections'])->map(fn ($s) => [
                'title' => $s['title'], 'description' => $s['description'] ?? null,
                'fields' => collect($s['fields'])->map(fn ($f) => $this->answerView($f, $reg->answers[$f['key']] ?? null, $docs))->all(),
            ])->all(),
            'canonical' => $canonical,
            'problems' => $problems,
            'missing_documents' => array_values(array_diff($required, $have)),
            'duplicates' => $pending ? $this->duplicates->find($prog, $canonical['aytam']) : [],
            'documents' => $docs->values()->map(fn ($d) => AytamController::presentDocument($d))->all(),
            'history' => $reg->events()->get()->map(fn ($e) => ['type' => $e->type, 'actor_name' => $e->actor_name, 'note' => $e->note, 'data' => $e->data, 'at' => $e->created_at?->toIso8601String()])->all(),
        ]);
    }

    public function approve(Request $request, string $program, string $registration): JsonResponse
    {
        /** @var Program $prog */
        $prog = $request->attributes->get('program');
        $reg = $this->find($prog, $registration);
        $data = $request->validate([
            'overrides' => ['nullable', 'array'], 'duplicate_decision' => ['nullable', Rule::in(['create_new', 'use_existing'])],
            'existing_aytam_id' => ['nullable', 'uuid'], 'family_id' => ['nullable', 'uuid'], 'guardian_id' => ['nullable', 'uuid'],
        ]);

        try {
            $aytam = $this->service->approve($reg, $prog, $request->user(), $data);
        } catch (DuplicatesFound $e) {
            return ApiResponse::error('DUPLICATES_FOUND', $e->getMessage(), 409, ['matches' => $e->matches]);
        } catch (RejectChange $e) {
            return ApiResponse::error(strtoupper($e->reason), $e->getMessage(), 422);
        }

        return ApiResponse::ok(['aytam' => ['id' => $aytam->getKey(), 'aytam_code' => $aytam->aytam_code, 'name' => $aytam->fullName(), 'status' => $aytam->status], 'registration' => $this->brief($reg->refresh())]);
    }

    public function sendBack(Request $request, string $program, string $registration): JsonResponse
    {
        $prog = $request->attributes->get('program');
        $reg = $this->find($prog, $registration);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        try {
            $this->service->sendBack($reg, $request->user(), $data['note']);
        } catch (RejectChange $e) {
            return ApiResponse::error(strtoupper($e->reason), $e->getMessage(), 422);
        }

        return ApiResponse::ok($this->brief($reg->refresh()));
    }

    private function find(Program $program, string $id): Registration
    {
        return Registration::query()->where('program_id', $program->getKey())->with('formVersion')->findOrFail($id);
    }

    private function brief(Registration $r): array
    {
        return [
            'id' => $r->getKey(), 'reference' => $r->reference, 'status' => $r->status, 'applicant_name' => $r->applicant_name,
            'form' => $r->relationLoaded('form') && $r->form ? ['id' => $r->form->id, 'title' => $r->form->title] : null,
            'submitted_at' => $r->submitted_at?->toIso8601String(), 'last_submitted_at' => $r->last_submitted_at?->toIso8601String(),
            'submission_count' => $r->submission_count, 'aytam_id' => $r->aytam_id, 'reviewed_at' => $r->reviewed_at?->toIso8601String(),
        ];
    }

    /** One question with its answer, ready to display. */
    private function answerView(array $field, mixed $answer, $docs): array
    {
        $view = ['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'required' => $field['required'], 'maps_to' => $field['maps_to'] ?? null];
        if (FieldTypes::isFile($field['type'])) {
            $doc = is_array($answer) ? ($docs[$answer['document_id'] ?? null] ?? null) : null;

            return $view + ['answer' => null, 'document' => $doc ? AytamController::presentDocument($doc) : null];
        }
        $text = match (true) {
            $answer === null => null,
            $field['type'] === 'address' => implode(', ', array_filter(array_map(fn ($p) => $answer[$p] ?? null, array_reverse(\App\Modules\Aytam\Registration\CanonicalFields::addressParts())))),
            is_array($answer) => implode(', ', $answer),
            default => (string) $answer,
        };

        return $view + ['answer' => $text, 'document' => null];
    }
}
