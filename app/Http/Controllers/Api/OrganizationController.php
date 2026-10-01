<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\ProgramOrganization;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The foundation-level master of partner organizations. Program-specific terms live on the program link, not here. */
class OrganizationController extends Controller
{
    private function rules(bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];

        return [
            'name' => [...$req, 'string', 'max:200'],
            'type' => [...$req, Rule::in(Organization::TYPES)],
            'country' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(Organization::TYPES)], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);

        $orgs = Organization::query()->withCount('programLinks')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('country', 'like', "%{$t}%")))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')->get();

        return ApiResponse::ok($orgs->map(fn (Organization $o) => $this->present($o) + ['programs_count' => $o->program_links_count])->all());
    }

    public function show(Organization $organization): JsonResponse
    {
        $links = ProgramOrganization::query()->where('organization_id', $organization->getKey())->with('program:id,name,slug,category,status')->get();

        return ApiResponse::ok($this->present($organization, withContacts: true) + [
            'programs' => $links->map(fn (ProgramOrganization $l) => [
                'program_id' => $l->program_id, 'name' => $l->program?->name, 'category' => $l->program?->category, 'relationship' => $l->relationship, 'status' => $l->status,
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $org = new Organization;
        $org->forceFill($request->validate($this->rules(true)) + ['status' => 'active'])->save();

        return ApiResponse::created($this->present($org->refresh(), withContacts: true));
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $organization->forceFill($request->validate($this->rules(false)))->save();

        return ApiResponse::ok($this->present($organization->refresh(), withContacts: true));
    }

    /** Removing an organization that programs still use would break their partner lists: deactivate instead. */
    public function destroy(Organization $organization): JsonResponse
    {
        if ($organization->programLinks()->exists()) {
            return ApiResponse::error('IN_USE', 'This organization is linked to programs. Deactivate it instead, or unlink it first.', 409);
        }
        $organization->delete();

        return ApiResponse::ok(null);
    }

    // ---- contact persons ----

    public function storeContact(Request $request, Organization $organization): JsonResponse
    {
        $contact = new OrganizationContact;
        $contact->forceFill($request->validate($this->contactRules(true)) + ['organization_id' => $organization->getKey()])->save();
        $this->singlePrimary($contact);

        return ApiResponse::created($this->presentContact($contact->refresh()));
    }

    public function updateContact(Request $request, Organization $organization, string $contact): JsonResponse
    {
        $model = $organization->contacts()->findOrFail($contact);
        $model->forceFill($request->validate($this->contactRules(false)))->save();
        $this->singlePrimary($model);

        return ApiResponse::ok($this->presentContact($model->refresh()));
    }

    public function destroyContact(Organization $organization, string $contact): JsonResponse
    {
        $organization->contacts()->findOrFail($contact)->delete();

        return ApiResponse::ok(null);
    }

    private function contactRules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Only one primary contact per organization. */
    private function singlePrimary(OrganizationContact $contact): void
    {
        if ($contact->is_primary) {
            OrganizationContact::query()->where('organization_id', $contact->organization_id)->where('id', '!=', $contact->getKey())->where('is_primary', true)
                ->get()->each(fn (OrganizationContact $c) => $c->update(['is_primary' => false]));
        }
    }

    private function present(Organization $o, bool $withContacts = false): array
    {
        $out = [
            'id' => $o->getKey(), 'name' => $o->name, 'type' => $o->type, 'country' => $o->country, 'location' => $o->location, 'address' => $o->address,
            'email' => $o->email, 'phone' => $o->phone, 'website' => $o->website, 'notes' => $o->notes, 'status' => $o->status,
            'version' => $o->version, 'created_at' => $o->created_at?->toIso8601String(), 'updated_at' => $o->updated_at?->toIso8601String(),
        ];

        return $withContacts ? $out + ['contacts' => $o->contacts->map(fn ($c) => $this->presentContact($c))->all()] : $out;
    }

    private function presentContact(OrganizationContact $c): array
    {
        return ['id' => $c->getKey(), 'name' => $c->name, 'title' => $c->title, 'email' => $c->email, 'phone' => $c->phone, 'is_primary' => $c->is_primary, 'notes' => $c->notes];
    }
}
