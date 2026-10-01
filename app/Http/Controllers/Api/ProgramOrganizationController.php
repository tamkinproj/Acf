<?php

namespace App\Http\Controllers\Api;

use App\Core\Audit\Auditor;
use App\Core\Programs\ProgramAccess;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Program;
use App\Models\ProgramOrganization;
use App\Support\ApiResponse;
use App\Tenancy\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Which organizations work with a program, and on what terms. The organization itself stays a foundation-level record. */
class ProgramOrganizationController extends Controller
{
    public function __construct(private ProgramAccess $access, private Auditor $auditor) {}

    public function index(Request $request, Program $program): JsonResponse
    {
        abort_unless($this->access->canView($request->user(), $program), 404);

        $links = ProgramOrganization::query()->where('program_id', $program->getKey())->with('organization.contacts')->get()
            ->sortBy(fn ($l) => $l->organization?->name)->values();

        return ApiResponse::ok([
            'links' => $links->map(fn (ProgramOrganization $l) => $this->present($l))->all(),
            'can_configure' => $this->access->canConfigure($request->user(), $program),
        ]);
    }

    public function store(Request $request, Program $program): JsonResponse
    {
        $this->authorizeConfigure($request, $program);
        $data = $request->validate([
            'organization_id' => ['required', 'uuid', TenantRule::exists(Organization::class)],
            'relationship' => ['sometimes', Rule::in(ProgramOrganization::RELATIONSHIPS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'config' => ['sometimes', 'array'],
        ]);
        if (ProgramOrganization::query()->where('program_id', $program->getKey())->where('organization_id', $data['organization_id'])->exists()) {
            return ApiResponse::error('ALREADY_LINKED', 'This organization is already linked to the program.', 409);
        }
        $link = new ProgramOrganization;
        $link->forceFill($data + ['program_id' => $program->getKey(), 'status' => 'active'])->save();

        return ApiResponse::created($this->present($link->load('organization.contacts')));
    }

    public function update(Request $request, Program $program, string $link): JsonResponse
    {
        $this->authorizeConfigure($request, $program);
        $model = ProgramOrganization::query()->where('program_id', $program->getKey())->findOrFail($link);
        $model->forceFill($request->validate([
            'relationship' => ['sometimes', Rule::in(ProgramOrganization::RELATIONSHIPS)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'config' => ['sometimes', 'array'],
        ]))->save();

        return ApiResponse::ok($this->present($model->load('organization.contacts')));
    }

    public function destroy(Request $request, Program $program, string $link): JsonResponse
    {
        $this->authorizeConfigure($request, $program);
        $model = ProgramOrganization::query()->where('program_id', $program->getKey())->with('organization:id,name')->findOrFail($link);
        $label = $model->auditLabel();
        $model->delete();
        $this->auditor->record('program_organization.removed', "Unlinked {$label}", 'program_organizations', $model->getKey());

        return ApiResponse::ok(null);
    }

    private function authorizeConfigure(Request $request, Program $program): void
    {
        abort_unless($this->access->canView($request->user(), $program), 404);
        abort_unless($this->access->canConfigure($request->user(), $program), 403, 'You do not have permission to manage this program\'s partners.');
        abort_if($program->status === Program::ARCHIVED, 409, 'This program is archived.');
    }

    private function present(ProgramOrganization $l): array
    {
        $o = $l->organization;

        return [
            'id' => $l->getKey(), 'program_id' => $l->program_id, 'organization_id' => $l->organization_id, 'relationship' => $l->relationship,
            'status' => $l->status, 'notes' => $l->notes, 'config' => $l->config ?? (object) [],
            'organization' => $o ? ['id' => $o->id, 'name' => $o->name, 'type' => $o->type, 'country' => $o->country, 'email' => $o->email, 'phone' => $o->phone,
                'status' => $o->status, 'contacts' => $o->contacts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'title' => $c->title, 'email' => $c->email, 'phone' => $c->phone, 'is_primary' => $c->is_primary])->all()] : null,
        ];
    }
}
