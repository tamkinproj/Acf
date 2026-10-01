<?php

namespace App\Http\Controllers\Api\Platform;

use App\Core\Access\RoleCatalog;
use App\Core\Audit\Auditor;
use App\Core\Users\PasswordPolicy;
use App\Core\Users\SessionRevoker;
use App\Core\Users\UserRules;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Platform administrators. A separate list from foundation users: the platform scope only ever sees its own accounts. */
class PlatformUserController extends Controller
{
    public function __construct(private Auditor $auditor, private SessionRevoker $sessions) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok(User::query()->orderBy('name')->get()->map(fn (User $u) => $this->present($u))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190', UserRules::uniqueEmail(null)],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $temporary = PasswordPolicy::generateTemporary();
        $role = Role::query()->where('key', RoleCatalog::PLATFORM_ADMIN)->firstOrFail();

        $user = new User;
        $user->forceFill($data + ['email' => strtolower($data['email']), 'password' => $temporary, 'role_id' => $role->getKey(), 'status' => 'active', 'must_change_password' => true])->save();

        return ApiResponse::created($this->present($user) + ['temporary_password' => $temporary]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:190', UserRules::uniqueEmail($user)],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
        ]);
        if (($data['status'] ?? 'active') !== 'active') {
            $this->guardLoss($request, $user);
        }
        $user->forceFill(isset($data['email']) ? array_merge($data, ['email' => strtolower($data['email'])]) : $data)->save();
        if (($data['status'] ?? 'active') === 'disabled') {
            $this->sessions->revokeAll($user->getKey());
        }

        return ApiResponse::ok($this->present($user->refresh()));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->guardLoss($request, $user);
        $this->sessions->revokeAll($user->getKey());
        $user->delete();

        return ApiResponse::ok(null);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $temporary = PasswordPolicy::generateTemporary();
        $user->forceFill(['password' => $temporary, 'must_change_password' => true])->save();
        $this->sessions->revokeAll($user->getKey());
        $this->auditor->record('user.password_reset', "Reset password for platform administrator \"{$user->name}\"", 'users', $user->getKey());

        return ApiResponse::ok(['temporary_password' => $temporary]);
    }

    private function guardLoss(Request $request, User $user): void
    {
        if ($user->getKey() === $request->user()->getKey()) {
            abort(ApiResponse::error('FORBIDDEN', 'You cannot disable or remove your own account.', 403));
        }
        if (User::query()->where('status', 'active')->where('id', '!=', $user->getKey())->count() === 0) {
            abort(ApiResponse::error('LAST_PLATFORM_ADMIN', 'The last active platform administrator cannot be disabled or removed.', 403));
        }
    }

    private function present(User $u): array
    {
        return ['id' => $u->getKey(), 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'status' => $u->status,
            'must_change_password' => $u->must_change_password, 'last_login_at' => $u->last_login_at?->toIso8601String(), 'created_at' => $u->created_at?->toIso8601String()];
    }
}
