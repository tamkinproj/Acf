<?php

namespace App\Http\Controllers\Api;

use App\Core\Audit\Auditor;
use App\Core\Users\PasswordPolicy;
use App\Core\Users\SessionRevoker;
use App\Core\Users\UserRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Sync\RejectChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Online user administration. Creating a user needs a password hash, which
 * never replicates, so creation lives here and not in the sync API. Field
 * edits and deletes share UserRules with the sync engine.
 */
class UserController extends Controller
{
    public function __construct(private Auditor $auditor, private SessionRevoker $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,disabled'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);

        $page = User::query()->with('role:id,key,name')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')->paginate((int) $request->input('per_page', 25));

        return ApiResponse::ok($page->getCollection()->map(fn (User $u) => $this->present($u))->all(), [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $memberships = $user->programMemberships()->with(['program:id,name,slug,category,status', 'role:id,key,name'])->get();

        return ApiResponse::ok($this->present($user->load('role:id,key,name')) + ['programs' => $memberships->map(fn ($m) => [
            'program_id' => $m->program_id, 'name' => $m->program?->name, 'category' => $m->program?->category, 'status' => $m->program?->status,
            'role' => $m->role ? ['id' => $m->role->id, 'key' => $m->role->key, 'name' => $m->role->name] : null,
        ])->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $fields = $this->validated($request->all(), 'create', null);
        $this->guard($request, 'create', null, $fields);

        $temporary = PasswordPolicy::generateTemporary();
        $user = new User;
        $user->forceFill($fields + ['email' => strtolower($fields['email']), 'password' => $temporary, 'must_change_password' => true, 'status' => $fields['status'] ?? 'active'])->save();

        // The temporary password is shown exactly once, to the administrator, and never stored in clear text.
        return ApiResponse::created($this->present($user->load('role:id,key,name')) + ['temporary_password' => $temporary]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $fields = $this->validated($request->only(['name', 'email', 'phone', 'role_id', 'status', 'locale']), 'update', $user);
        $this->guard($request, 'update', $user, $fields);

        if (isset($fields['email'])) {
            $fields['email'] = strtolower($fields['email']);
        }
        $user->forceFill($fields)->save();
        if (($fields['status'] ?? 'active') === 'disabled') {
            $this->sessions->revokeAll($user->getKey());
        }

        return ApiResponse::ok($this->present($user->fresh('role:id,key,name')));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->guard($request, 'delete', $user, []);
        $this->sessions->revokeAll($user->getKey());
        $user->delete();

        return ApiResponse::ok(null);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->guard($request, 'update', $user, []);
        $temporary = PasswordPolicy::generateTemporary();
        $user->forceFill(['password' => $temporary, 'must_change_password' => true])->save();
        $this->sessions->revokeAll($user->getKey());
        $this->auditor->record('user.password_reset', "Reset password for \"{$user->name}\"", 'users', $user->getKey());

        return ApiResponse::ok(['temporary_password' => $temporary]);
    }

    private function validated(array $input, string $op, ?User $existing): array
    {
        $rules = UserRules::rules($op, $existing);
        $rules = $op === 'update' ? array_intersect_key($rules, $input) : $rules;

        return Validator::make($input, $rules)->validate();
    }

    private function guard(Request $request, string $op, ?User $target, array $fields): void
    {
        try {
            UserRules::guard($request->user(), $op, $target, $fields);
        } catch (RejectChange $e) {
            abort(ApiResponse::error(strtoupper($e->reason), $e->getMessage(), 403));
        }
    }

    private function present(User $u): array
    {
        return [
            'id' => $u->getKey(), 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'status' => $u->status,
            'locale' => $u->locale, 'role' => $u->role ? ['id' => $u->role->id, 'key' => $u->role->key, 'name' => $u->role->name] : null,
            'must_change_password' => $u->must_change_password, 'last_login_at' => $u->last_login_at?->toIso8601String(),
            'version' => $u->version, 'created_at' => $u->created_at?->toIso8601String(),
        ];
    }
}
