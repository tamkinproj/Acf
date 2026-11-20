<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Guardian;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * The Security Admin's job: accounts, roles, passwords, sessions. Never records.
 * Safeguards: no one can be made System Admin here, a System Admin account cannot be edited here,
 * and nobody can change their own role or switch themselves off.
 */
class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('department')->where('foundation_id', $request->user()->foundation_id)
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')->paginate(50);
        $users->setCollection($users->getCollection()->map->toApi());

        return ApiResponse::collection($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true));
        $actor = $request->user();
        $link = $this->links($request, $data['role'], $data);

        if ($link instanceof \Illuminate\Http\JsonResponse) {
            return $link;
        }

        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => $data['role'],
            'foundation_id' => $actor->foundation_id, 'phone' => $data['phone'] ?? null, 'active' => true,
        ] + $link);

        Audit::record('user.created', $request, 'user', $user->id, ['role' => $user->role]);

        return ApiResponse::created($user->load('department')->toApi());
    }

    public function update(Request $request, User $user)
    {
        if ($denied = $this->guard($request, $user)) {
            return $denied;
        }

        $data = $request->validate($this->rules(false, $user->id));
        $changesAccess = array_key_exists('role', $data) || array_key_exists('active', $data) || array_key_exists('department_key', $data);

        if ($user->id === $request->user()->id && (isset($data['role']) && $data['role'] !== $user->role || isset($data['active']) && ! $data['active'])) {
            return ApiResponse::error('SELF_CHANGE', 'You cannot change your own role or switch yourself off.', 422);
        }

        $role = $data['role'] ?? $user->role;
        $link = $this->links($request, $role, $data + ['guardian_id' => $user->guardian_id, 'department_key' => $data['department_key'] ?? $user->department_key]);
        if ($link instanceof \Illuminate\Http\JsonResponse) {
            return $link;
        }

        $user->fill(collect($data)->only(['name', 'email', 'phone', 'active'])->all() + ['role' => $role] + $link)->save();

        if ($changesAccess) {
            $user->tokens()->delete();   // new access applies from the next sign-in
        }

        Audit::record('user.updated', $request, 'user', $user->id, ['fields' => array_keys($data)]);

        return ApiResponse::updated($user->load('department')->toApi());
    }

    public function resetPassword(Request $request, User $user)
    {
        if ($denied = $this->guard($request, $user)) {
            return $denied;
        }

        $data = $request->validate(['password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        Audit::record('user.password_reset', $request, 'user', $user->id);

        return ApiResponse::deleted('Password changed and all sessions ended.');
    }

    public function revokeTokens(Request $request, User $user)
    {
        if ($denied = $this->guard($request, $user)) {
            return $denied;
        }

        $user->tokens()->delete();
        Audit::record('user.sessions_revoked', $request, 'user', $user->id);

        return ApiResponse::deleted('All sessions ended.');
    }

    /** Other foundation => it does not exist for this admin. System Admin accounts are out of reach. */
    private function guard(Request $request, User $user): ?\Illuminate\Http\JsonResponse
    {
        if ($user->foundation_id !== $request->user()->foundation_id) {
            return ApiResponse::notFound('No such user.');
        }
        if ($user->role === 'system_admin') {
            return ApiResponse::forbidden('System Admin accounts cannot be changed here.');
        }

        return null;
    }

    /** Works out department / guardian for the role, or a 422 explaining what is missing. */
    private function links(Request $request, string $role, array $data): array|\Illuminate\Http\JsonResponse
    {
        $out = ['department_id' => null, 'guardian_id' => null];

        if ($role === 'staff') {
            $dept = Department::where('foundation_id', $request->user()->foundation_id)->where('key', $data['department_key'] ?? null)->first();
            if (! $dept) {
                return ApiResponse::error('DEPARTMENT_REQUIRED', 'Staff need a valid department.', 422);
            }
            $out['department_id'] = $dept->id;
        }
        if ($role === 'user') {
            $guardian = ! empty($data['guardian_id']) ? Guardian::where('id', $data['guardian_id'])->first() : null;
            if (! $guardian) {
                return ApiResponse::error('GUARDIAN_REQUIRED', 'A family account must be linked to a guardian in this foundation.', 422);
            }
            $out['guardian_id'] = $guardian->id;
        }

        return $out;
    }

    private function rules(bool $creating, ?int $ignoreId = null): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:150'],
            'email' => [$req, 'email:rfc,filter', 'max:190', 'unique:users,email'.($ignoreId ? ",$ignoreId" : '')],
            'password' => $creating ? ['required', Password::min(12)->mixedCase()->numbers()] : ['prohibited'],
            'role' => [$req, 'in:'.implode(',', config('roles.assignable_by_security_admin'))],
            'department_key' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('roles.departments')))],
            'guardian_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:40'],
            'active' => $creating ? ['prohibited'] : ['sometimes', 'boolean'],
        ];
    }
}
