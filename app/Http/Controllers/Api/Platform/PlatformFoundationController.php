<?php

namespace App\Http\Controllers\Api\Platform;

use App\Core\Audit\Auditor;
use App\Core\Platform\FoundationProvisioner;
use App\Core\Platform\FoundationService;
use App\Core\Access\RoleCatalog;
use App\Core\Settings\SettingsCatalog;
use App\Core\Users\PasswordPolicy;
use App\Core\Users\SessionRevoker;
use App\Core\Users\UserRules;
use App\Http\Controllers\Controller;
use App\Models\Foundation;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Foundation lifecycle. The platform creates, edits and switches foundations on or off, and can hand a foundation
 * administrator a new temporary password. It never reads a foundation's records.
 */
class PlatformFoundationController extends Controller
{
    public function __construct(private TenantContext $tenant, private Auditor $auditor, private SessionRevoker $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(Foundation::STATUSES)], 'per_page' => ['nullable', 'integer', 'between:1,100']]);

        $page = Foundation::query()
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('short_name', 'like', "%{$t}%")->orWhere('slug', 'like', "%{$t}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')->paginate((int) $request->input('per_page', 25));

        $ids = $page->pluck('id')->all();
        $users = DB::table('users')->whereIn('foundation_id', $ids)->whereNull('deleted_at')->groupBy('foundation_id')->selectRaw('foundation_id, count(*) c')->pluck('c', 'foundation_id');
        $programs = DB::table('programs')->whereIn('foundation_id', $ids)->whereNull('deleted_at')->groupBy('foundation_id')->selectRaw('foundation_id, count(*) c')->pluck('c', 'foundation_id');

        return ApiResponse::ok($page->getCollection()->map(fn (Foundation $f) => $this->present($f) + [
            'users_count' => (int) ($users[$f->id] ?? 0), 'programs_count' => (int) ($programs[$f->id] ?? 0),
        ])->all(), ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]);
    }

    public function show(Foundation $foundation): JsonResponse
    {
        return ApiResponse::ok($this->detail($foundation));
    }

    public function store(Request $request, FoundationProvisioner $provisioner): JsonResponse
    {
        $data = $request->validate($this->rules(null) + [
            'admin' => ['required', 'array'],
            'admin.name' => ['required', 'string', 'max:150'],
            'admin.email' => ['required', 'email:rfc', 'max:190', UserRules::uniqueEmail(null)],
            'admin.phone' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $provisioner->create($data, $data['admin'], $request->user());
        $f = $result['foundation'];
        $this->auditor->record('foundation.created', "Created foundation \"{$f->name}\"", 'foundations', $f->getKey(), null, ['name' => $f->name, 'slug' => $f->slug], $request->user());

        // The temporary password is shown exactly once, to the platform administrator handing it over.
        return ApiResponse::created($this->detail($f) + ['administrator' => ['name' => $result['admin']->name, 'email' => $result['admin']->email, 'temporary_password' => $result['temporary_password']]]);
    }

    public function update(Request $request, Foundation $foundation): JsonResponse
    {
        $fields = Validator::make($request->only(array_keys($this->rules($foundation))), array_intersect_key($this->rules($foundation), $request->all()))->validate();
        $foundation->forceFill($fields)->save();

        return ApiResponse::ok($this->detail($foundation->refresh()));
    }

    public function status(Request $request, Foundation $foundation, FoundationService $service): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(Foundation::STATUSES)], 'reason' => ['nullable', 'string', 'max:255']]);

        return ApiResponse::ok($this->detail($service->setStatus($foundation, $data['status'], $data['reason'] ?? null, $request->user())));
    }

    /** Add another administrator to a foundation (support: the original one left or is locked out). */
    public function addAdmin(Request $request, Foundation $foundation): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190', UserRules::uniqueEmail(null)],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $temporary = PasswordPolicy::generateTemporary();

        $user = $this->tenant->runAs($foundation->getKey(), function () use ($data, $temporary) {
            $role = Role::query()->where('key', RoleCatalog::FOUNDATION_ADMIN)->firstOrFail();
            $user = new User;
            $user->forceFill($data + ['password' => $temporary, 'email' => strtolower($data['email']), 'role_id' => $role->getKey(), 'status' => 'active', 'must_change_password' => true])->save();

            return $user;
        });
        $this->auditor->record('foundation.admin_added', "Added administrator \"{$user->name}\" to \"{$foundation->name}\"", 'foundations', $foundation->getKey(), null, ['email' => $user->email], $request->user());

        return ApiResponse::created(['id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email, 'temporary_password' => $temporary]);
    }

    public function resetAdminPassword(Request $request, Foundation $foundation, string $user): JsonResponse
    {
        $temporary = PasswordPolicy::generateTemporary();
        $target = $this->tenant->runAs($foundation->getKey(), function () use ($user, $temporary) {
            $target = User::query()->where('id', $user)->whereHas('role', fn ($q) => $q->where('key', RoleCatalog::FOUNDATION_ADMIN))->firstOrFail();
            $target->forceFill(['password' => $temporary, 'must_change_password' => true])->save();

            return $target;
        });
        $this->sessions->revokeAll($target->getKey());
        $this->auditor->record('foundation.admin_password_reset', "Reset the password of administrator \"{$target->name}\" ({$foundation->name})", 'foundations', $foundation->getKey(), null, null, $request->user());

        return ApiResponse::ok(['temporary_password' => $temporary]);
    }

    private function rules(?Foundation $existing): array
    {
        $req = $existing ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$req, 'string', 'max:200'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'timezone' => [Rule::prohibitedIf($existing !== null), 'nullable', Rule::in(\DateTimeZone::listIdentifiers())],
            'locale' => [Rule::prohibitedIf($existing !== null), 'nullable', Rule::in(SettingsCatalog::LOCALES)],
        ];
    }

    private function present(Foundation $f): array
    {
        return [
            'id' => $f->getKey(), 'name' => $f->name, 'legal_name' => $f->legal_name, 'short_name' => $f->short_name, 'slug' => $f->slug,
            'description' => $f->description, 'email' => $f->email, 'phone' => $f->phone, 'address' => $f->address, 'country' => $f->country,
            'website' => $f->website, 'registration_number' => $f->registration_number,
            'status' => $f->status, 'status_reason' => $f->status_reason, 'status_changed_at' => $f->status_changed_at?->toIso8601String(),
            'created_at' => $f->created_at?->toIso8601String(), 'updated_at' => $f->updated_at?->toIso8601String(),
        ];
    }

    /** Profile, administrators and headline numbers - never the foundation's records. */
    private function detail(Foundation $f): array
    {
        $id = $f->getKey();
        $admins = DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.foundation_id', $id)->where('roles.key', RoleCatalog::FOUNDATION_ADMIN)->whereNull('users.deleted_at')
            ->get(['users.id', 'users.name', 'users.email', 'users.status', 'users.last_login_at']);

        return $this->present($f) + [
            'administrators' => $admins->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'email' => $a->email, 'status' => $a->status, 'last_login_at' => $a->last_login_at])->all(),
            'usage' => [
                'users' => (int) DB::table('users')->where('foundation_id', $id)->whereNull('deleted_at')->count(),
                'programs' => (int) DB::table('programs')->where('foundation_id', $id)->whereNull('deleted_at')->count(),
                'active_programs' => (int) DB::table('programs')->where('foundation_id', $id)->whereNull('deleted_at')->where('status', 'active')->count(),
                'organizations' => (int) DB::table('organizations')->where('foundation_id', $id)->whereNull('deleted_at')->count(),
                'devices' => (int) DB::table('devices')->where('foundation_id', $id)->whereNull('deleted_at')->count(),
                'storage_bytes' => (int) DB::table('documents')->where('foundation_id', $id)->whereNull('deleted_at')->sum('size'),
            ],
        ];
    }
}
