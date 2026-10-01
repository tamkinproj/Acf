<?php

namespace App\Http\Controllers\Api\Aytam;

use App\Core\Audit\Auditor;
use App\Http\Controllers\Controller;
use App\Models\Aytam;
use App\Models\AytamAssignment;
use App\Models\Document;
use App\Models\Program;
use App\Models\ProgramUser;
use App\Models\User;
use App\Modules\Aytam\AytamAccess;
use App\Modules\Aytam\AytamRules;
use App\Modules\Aytam\AytamService;
use App\Modules\Aytam\AytamWorkflow;
use App\Support\ApiResponse;
use App\Sync\RejectChange;
use App\Tenancy\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AytamController extends Controller
{
    public function __construct(private AytamAccess $access, private AytamService $service, private Auditor $auditor) {}

    public function index(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(Aytam::STATUSES)], 'gender' => ['nullable', Rule::in(['male', 'female'])],
            'family_id' => ['nullable', 'uuid'], 'guardian_id' => ['nullable', 'uuid'], 'assigned_to' => ['nullable', 'uuid'],
            'sort' => ['nullable', Rule::in(['code', 'name', 'dob', 'created', 'status'])], 'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $sort = ['code' => 'aytam_code', 'name' => 'normalized_name', 'dob' => 'date_of_birth', 'created' => 'created_at', 'status' => 'status'][$request->input('sort', 'code')];
        $page = $this->access->visible($request->user(), $program)->with('family:id,name')
            ->when($request->q, function ($q, $term) {
                $norm = \App\Modules\Aytam\NameNormalizer::name($term);
                $q->where(fn ($w) => $w->where('aytam_code', 'like', "%{$term}%")->orWhere('arabic_name', 'like', "%{$term}%")->orWhere('legacy_ref', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('middle_name', 'like', "%{$term}%")
                    ->when($norm !== '', fn ($n) => $n->orWhere('normalized_name', 'like', '%'.str_replace(' ', '%', $norm).'%')));
            })
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->gender, fn ($q, $v) => $q->where('gender', $v))
            ->when($request->family_id, fn ($q, $v) => $q->where('family_id', $v))
            ->when($request->guardian_id, fn ($q, $v) => $q->where('guardian_id', $v))
            ->when($request->assigned_to, fn ($q, $v) => $q->whereIn('id', AytamAssignment::query()->where('user_id', $v)->select('aytam_id')))
            ->orderBy($sort, $request->input('dir', 'asc'))->orderBy('aytam_code')
            ->paginate((int) $request->input('per_page', 25));

        return ApiResponse::ok($page->getCollection()->map(fn (Aytam $a) => $this->brief($a))->all(), [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
            'sees_all' => $this->access->seesAll($request->user(), $program),
        ]);
    }

    public function show(Request $request, Program $program, string $aytam): JsonResponse
    {
        $record = $this->access->find($request->user(), $program, $aytam);

        return ApiResponse::ok($this->detail($request->user(), $program, $record));
    }

    public function store(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $user = $request->user();
        $data = $request->validate(AytamRules::rules($program, true) + ['status' => ['sometimes', Rule::in([Aytam::DRAFT, Aytam::ACTIVE])]]);

        // Only a reviewer may create a record that is already live; everyone else starts a draft.
        $status = ($data['status'] ?? Aytam::DRAFT) === Aytam::ACTIVE && $user->hasPermission('aytam.review', $program) ? Aytam::ACTIVE : Aytam::DRAFT;
        $aytam = $this->service->create($program, $data, $user, 'manual', null, $status);

        return ApiResponse::created($this->detail($user, $program, $aytam));
    }

    public function update(Request $request, Program $program, string $aytam): JsonResponse
    {
        $user = $request->user();
        $record = $this->access->find($user, $program, $aytam);
        if ($record->status === Aytam::ARCHIVED) {
            return ApiResponse::error('ARCHIVED', 'An archived record cannot be edited. Restore it first.', 409);
        }

        $allowed = $this->access->editableFields($user, $program);
        $sent = array_keys($request->only(AytamRules::fields()));
        if ($forbidden = array_values(array_diff($sent, $allowed))) {
            return ApiResponse::error('FIELDS_NOT_ALLOWED', 'You may not change: '.implode(', ', $forbidden).'.', 403, ['fields' => $forbidden]);
        }
        $rules = array_intersect_key(AytamRules::rules($program, false), $request->only($allowed));
        $data = Validator::make($request->only($allowed), $rules)->validate();

        return ApiResponse::ok($this->detail($user, $program, $this->service->update($record, $data)));
    }

    public function status(Request $request, Program $program, string $aytam): JsonResponse
    {
        $user = $request->user();
        $record = $this->access->find($user, $program, $aytam);
        $data = $request->validate(['status' => ['required', Rule::in(Aytam::STATUSES)], 'note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->service->transition($record, $program, $data['status'], $user, $data['note'] ?? null);
        } catch (RejectChange $e) {
            return ApiResponse::error(strtoupper($e->reason), $e->getMessage(), $e->reason === 'forbidden' ? 403 : 422);
        }

        return ApiResponse::ok($this->detail($user, $program, $record->refresh()));
    }

    /** Replace the set of field workers assigned to a record. */
    public function assign(Request $request, Program $program, string $aytam): JsonResponse
    {
        $record = $this->access->find($request->user(), $program, $aytam);
        $data = $request->validate([
            'user_ids' => ['present', 'array', 'max:50'],
            'user_ids.*' => ['uuid', 'distinct', TenantRule::exists(User::class, 'id', fn ($q) => $q->where('status', 'active')->whereIn('id', ProgramUser::query()->where('program_id', $program->getKey())->select('user_id')))],
        ]);

        $current = AytamAssignment::query()->where('aytam_id', $record->getKey())->get();
        $wanted = collect($data['user_ids']);
        $current->reject(fn ($a) => $wanted->contains($a->user_id))->each(function (AytamAssignment $a) use ($record) {
            $a->delete();
            $this->auditor->record('aytam.unassigned', "Unassigned a worker from {$record->auditLabel()}", 'aytam', $record->getKey(), ['user_id' => $a->user_id]);
        });
        $wanted->reject(fn ($id) => $current->contains('user_id', $id))->each(function ($id) use ($record, $program, $request) {
            AytamAssignment::create(['program_id' => $program->getKey(), 'aytam_id' => $record->getKey(), 'user_id' => $id, 'assigned_by' => $request->user()->getKey()]);
            $this->auditor->record('aytam.assigned', "Assigned a worker to {$record->auditLabel()}", 'aytam', $record->getKey(), null, ['user_id' => $id]);
        });

        return ApiResponse::ok($this->assignments($record));
    }

    /** Program team members who can be given records. */
    public function assignable(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $members = ProgramUser::query()->where('program_id', $program->getKey())->with(['user:id,name,status', 'role:id,name'])->get()
            ->filter(fn ($m) => $m->user?->status === 'active')
            ->map(fn ($m) => ['user_id' => $m->user_id, 'name' => $m->user->name, 'role' => $m->role?->name])->sortBy('name')->values();

        return ApiResponse::ok($members->all());
    }

    // ---- presentation ----

    private function brief(Aytam $a): array
    {
        return [
            'id' => $a->getKey(), 'aytam_code' => $a->aytam_code, 'name' => $a->fullName(), 'arabic_name' => $a->arabic_name,
            'date_of_birth' => $a->date_of_birth?->toDateString(), 'gender' => $a->gender, 'status' => $a->status,
            'city' => $a->city, 'family' => $a->family ? ['id' => $a->family->id, 'name' => $a->family->name] : null, 'source' => $a->source,
        ];
    }

    private function detail(User $user, Program $program, Aytam $a): array
    {
        $a->loadMissing(['family.guardian', 'guardian']);
        $guardian = $a->guardian ?? $a->family?->guardian;
        $siblings = $a->family_id
            ? $this->access->visible($user, $program)->where('family_id', $a->family_id)->where('id', '!=', $a->getKey())->orderBy('date_of_birth')->get()->map(fn ($s) => $this->brief($s))->all()
            : [];
        $documents = Document::query()->where('aytam_id', $a->getKey())->where('is_current', true)->orderBy('type')->get();
        $required = $program->config['required_documents'] ?? [];

        $fields = collect($a->toArray())->only(AytamRules::fields() + ['id', 'aytam_code', 'status', 'status_note', 'source', 'legacy_ref'])->all();

        return [
            'id' => $a->getKey(), 'aytam_code' => $a->aytam_code, 'name' => $a->fullName(), 'status' => $a->status, 'status_note' => $a->status_note,
            'source' => $a->source, 'registration_id' => $a->registration_id, 'approved_at' => $a->approved_at?->toIso8601String(),
            'fields' => collect(AytamRules::fields())->mapWithKeys(fn ($f) => [$f => $f === 'date_of_birth' ? $a->date_of_birth?->toDateString() : $a->{$f}])->all(),
            'family' => $a->family ? ['id' => $a->family->id, 'name' => $a->family->name, 'father_name' => $a->family->father_name, 'father_status' => $a->family->father_status,
                'mother_name' => $a->family->mother_name, 'mother_status' => $a->family->mother_status] : null,
            'guardian' => $guardian ? ['id' => $guardian->id, 'full_name' => $guardian->full_name, 'relationship' => $guardian->relationship, 'phone' => $guardian->phone,
                'email' => $guardian->email, 'inherited' => $a->guardian_id === null] : null,
            'siblings' => $siblings,
            'documents' => $documents->map(fn (Document $d) => $this->presentDocument($d))->all(),
            'required_documents' => collect($required)->map(fn ($type) => ['type' => $type, 'missing' => ! $documents->contains(fn ($d) => $d->type === $type && $d->verification_status !== Document::REJECTED)])->all(),
            'assignments' => $this->assignments($a),
            'can' => [
                'update' => $user->hasPermission('aytam.update', $program), 'edit_all' => $this->access->seesAll($user, $program),
                'assign' => $user->hasPermission('aytam.assign', $program), 'review' => $user->hasPermission('aytam.review', $program),
                'upload' => $user->hasPermission('documents.upload', $program), 'verify' => $user->hasPermission('documents.verify', $program),
                'transitions' => AytamWorkflow::allowedFor($user, $program, $a->status),
            ],
            'version' => $a->version, 'created_at' => $a->created_at?->toIso8601String(), 'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }

    private function assignments(Aytam $a): array
    {
        return AytamAssignment::query()->where('aytam_id', $a->getKey())->with('user:id,name')->get()
            ->map(fn ($x) => ['user_id' => $x->user_id, 'name' => $x->user?->name])->sortBy('name')->values()->all();
    }

    public static function presentDocument(Document $d): array
    {
        return [
            'id' => $d->getKey(), 'type' => $d->type, 'original_name' => $d->original_name, 'mime' => $d->mime, 'size' => $d->size, 'doc_version' => $d->doc_version,
            'group_id' => $d->group_id, 'is_current' => $d->is_current, 'status' => $d->effectiveStatus(), 'verification_status' => $d->verification_status,
            'rejection_reason' => $d->rejection_reason, 'expires_on' => $d->expires_on?->toDateString(), 'verified_at' => $d->verified_at?->toIso8601String(),
            'uploaded_at' => $d->created_at?->toIso8601String(), 'notes' => $d->notes,
        ];
    }
}
