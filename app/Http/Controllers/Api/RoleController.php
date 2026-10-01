<?php

namespace App\Http\Controllers\Api;

use App\Core\Access\PermissionCatalog;
use App\Core\Access\RoleCatalog;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()->withCount('users')->orderBy('name')->get()->map(fn (Role $r) => [
            'id' => $r->id, 'key' => $r->key, 'name' => $r->name, 'description' => $r->description,
            'is_system' => $r->is_system, 'permissions' => $r->permissions, 'users_count' => $r->users_count, 'version' => $r->version,
        ]);

        return ApiResponse::ok($roles);
    }

    public function permissions(): JsonResponse
    {
        return ApiResponse::ok(collect(PermissionCatalog::all())->map(fn ($p, $key) => ['key' => $key] + $p)->values());
    }

    /** Replace a role's permission list. Super Admin is fixed and can never be edited. */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->key === RoleCatalog::SUPER_ADMIN) {
            return ApiResponse::error('FORBIDDEN', 'The Super Admin role always has every permission and cannot be edited.', 403);
        }
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::keys())],
        ]);

        $role->permissions = array_values(array_unique($data['permissions']));
        $role->save();   // replicates to devices and is audited by the model hooks

        return ApiResponse::ok(['id' => $role->id, 'key' => $role->key, 'permissions' => $role->permissions, 'version' => $role->version]);
    }
}
