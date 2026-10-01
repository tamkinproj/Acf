<?php

namespace App\Http\Controllers\Api;

use App\Core\Access\PermissionCatalog;
use App\Core\Audit\Auditor;
use App\Http\Controllers\Controller;
use App\Models\ProgramUser;
use App\Models\Role;
use App\Programs\ProgramModules;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The foundation's own roles. Foundation-level roles can hold foundation and program permissions; program roles hold
 * program permissions only and are assigned per program. Foundation Admin is fixed so nobody can lock themselves out.
 */
class RoleController extends Controller
{
    public function __construct(private Auditor $auditor) {}

    public function index(): JsonResponse
    {
        $roles = Role::query()->withCount('users')->orderByRaw("case scope when 'foundation' then 0 else 1 end")->orderBy('name')->get();
        $teams = ProgramUser::query()->selectRaw('role_id, count(*) as c')->groupBy('role_id')->pluck('c', 'role_id');

        return ApiResponse::ok($roles->map(fn (Role $r) => [
            'id' => $r->id, 'key' => $r->key, 'name' => $r->name, 'description' => $r->description,
            'is_system' => $r->is_system, 'is_fixed' => $r->isFixed(), 'scope' => $r->scope, 'module' => $r->module,
            'permissions' => $r->isFixed() ? PermissionCatalog::keysIn($r->scope === 'platform' ? ['platform'] : ['foundation', 'program']) : $r->permissions,
            'users_count' => $r->scope === 'program' ? (int) ($teams[$r->id] ?? 0) : $r->users_count,
            'version' => $r->version,
        ]));
    }

    /** The permissions a foundation can grant (platform permissions never appear here). */
    public function permissions(): JsonResponse
    {
        $all = collect(PermissionCatalog::all())->filter(fn ($p) => $p['scope'] !== PermissionCatalog::PLATFORM);

        return ApiResponse::ok($all->map(fn ($p, $key) => ['key' => $key] + $p)->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', Rule::in([PermissionCatalog::FOUNDATION, PermissionCatalog::PROGRAM])],
            'module' => ['nullable', Rule::in(array_keys(ProgramModules::all()))],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::keys())],
        ]);
        $this->assertGrantable($data['scope'], $data['permissions']);

        $key = Str::slug($data['name'], '_') ?: 'role';
        for ($i = 2; Role::query()->where('key', $key)->exists(); $i++) {
            $key = Str::slug($data['name'], '_').'_'.$i;
        }
        $role = Role::create([
            'key' => $key, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'is_system' => false,
            'scope' => $data['scope'], 'module' => $data['scope'] === 'program' ? ($data['module'] ?? null) : null,
            'permissions' => array_values(array_unique($data['permissions'])),
        ]);

        return ApiResponse::created(['id' => $role->id, 'key' => $role->key, 'name' => $role->name, 'scope' => $role->scope]);
    }

    /** Replace a role's permission list. Fixed roles (Foundation Admin) can never be edited. */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->isFixed()) {
            return ApiResponse::error('FORBIDDEN', 'This role always has every permission and cannot be edited.', 403);
        }
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::keys())],
        ]);
        $this->assertGrantable($role->scope, $data['permissions']);

        $role->permissions = array_values(array_unique($data['permissions']));
        $role->save();   // replicates to devices and is audited by the model hooks

        return ApiResponse::ok(['id' => $role->id, 'key' => $role->key, 'permissions' => $role->permissions, 'version' => $role->version]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system || $role->isFixed()) {
            return ApiResponse::error('FORBIDDEN', 'Built-in roles cannot be removed.', 403);
        }
        if ($role->users()->exists() || ProgramUser::query()->where('role_id', $role->id)->exists()) {
            return ApiResponse::error('IN_USE', 'This role is still assigned to people. Move them to another role first.', 409);
        }
        $role->delete();

        return ApiResponse::ok(null);
    }

    /** A foundation role may hold foundation and program permissions; a program role program permissions only. */
    private function assertGrantable(string $scope, array $permissions): void
    {
        $allowed = $scope === PermissionCatalog::PROGRAM ? [PermissionCatalog::PROGRAM] : [PermissionCatalog::FOUNDATION, PermissionCatalog::PROGRAM];
        foreach ($permissions as $key) {
            if (! in_array(PermissionCatalog::scopeOf($key), $allowed, true)) {
                abort(ApiResponse::error('INVALID_PERMISSION', "A {$scope} role cannot hold \"{$key}\".", 422));
            }
        }
    }
}
