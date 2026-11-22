<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Guardian;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * The Security Admin's job: accounts, roles, passwords, sessions. Never records.
 * Safeguards: no one can be made System Admin here, a System Admin account cannot be edited here,
 * and nobody can change their own role or switch themselves off.
 */
class UserAdminController extends Controller
{
    /** @var list<string|null> texts the new password must not contain (name, email, username of the account being made) */
    private array $avoid = [];

    public function index(Request $request)
    {
        $users = User::with('department')->where('foundation_id', $request->user()->foundation_id)
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')->paginate(50);
        $users->setCollection($users->getCollection()->map(fn ($u) => $this->shape($u)));

        return ApiResponse::collection($users);
    }

    /** An account as the Accounts screen shows it: the usual fields plus, for staff, which features are ticked. */
    private function shape(User $u): array
    {
        $o = $u->toApi();
        if ($u->role === 'staff') {
            $o['access'] = PermissionCatalog::ticked($u);
            $o['access_custom'] = ! empty($u->access_overrides);
        }

        return $o;
    }

    /** The features that can be ticked, grouped, with what each department gives by default. */
    public function accessCatalog()
    {
        return ApiResponse::ok([
            'groups' => collect(config('roles.access_groups'))->map(fn ($perms, $key) => ['key' => $key, 'permissions' => $perms])->values(),
            'defaults' => collect(array_keys(config('roles.departments')))->mapWithKeys(fn ($d) => [$d => PermissionCatalog::departmentDefaults($d)]),
        ]);
    }

    /** Account changes ask for the signed-in person's own password again, so a phone left unlocked cannot be used to make a back door. */
    private function stepUp(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $actor = $request->user();
        $key = 'stepup:'.$actor->id;
        $max = (int) config('security.step_up.attempts', 5);
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, $max)) {
            return ApiResponse::error('THROTTLED', 'Too many wrong passwords. Try again in '.\Illuminate\Support\Facades\RateLimiter::availableIn($key).' seconds.', 429);
        }
        $given = (string) $request->input('confirm_password', '');
        if ($given === '' || ! \Illuminate\Support\Facades\Hash::check($given, $actor->password)) {
            \Illuminate\Support\Facades\RateLimiter::hit($key, (int) config('security.step_up.minutes', 5) * 60);
            Audit::record('stepup.failed', $request, 'user', $actor->id);

            return ApiResponse::error('WRONG_PASSWORD', 'Type your own password to confirm this change.', 422, ['confirm_password' => ['Type your own password to confirm this change.']]);
        }
        \Illuminate\Support\Facades\RateLimiter::clear($key);

        return null;
    }

    public function store(Request $request)
    {
        $this->normaliseUsername($request);
        $this->avoid = [$request->input('name'), $request->input('email'), $request->input('username')];
        $data = $request->validate($this->rules(true));
        if ($denied = $this->stepUp($request)) {
            return $denied;
        }
        $actor = $request->user();
        $link = $this->links($request, $data['role'], $data);

        if ($link instanceof \Illuminate\Http\JsonResponse) {
            return $link;
        }

        if (array_key_exists('access', $data) && ($denied = $this->accessAllowed($request, $data['role']))) {
            return $denied;
        }

        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'username' => $data['username'] ?? null, 'password' => $data['password'], 'role' => $data['role'],
            'foundation_id' => $actor->foundation_id, 'phone' => $data['phone'] ?? null, 'active' => true,
        ] + $link);
        if (array_key_exists('access', $data)) {
            $diff = PermissionCatalog::setTicked($user, $data['access'], $data['department_key'] ?? null);
            $user->save();
            if ($diff['added'] || $diff['removed']) {
                Audit::record('user.access_changed', $request, 'user', $user->id, $diff);
            }
        }

        \App\Support\Notify::to($user->id, (int) $user->foundation_id, 'system', 'Welcome to Al Insania', 'Your account is ready. You will see messages and reminders for you here.', 'settings.html');
        Audit::record('user.created', $request, 'user', $user->id, ['role' => $user->role]);

        return ApiResponse::created($this->shape($user->load('department')));
    }

    public function update(Request $request, User $user)
    {
        if ($denied = $this->guard($request, $user)) {
            return $denied;
        }

        $this->normaliseUsername($request);
        $data = $request->validate($this->rules(false, $user->id));
        if (array_key_exists('username', $data) && ! $data['username']) {
            unset($data['username']);   // an empty box means "leave it"
        }
        if (array_intersect(['role', 'active', 'department_key', 'email', 'access'], array_keys($data)) && ($denied = $this->stepUp($request))) {
            return $denied;
        }
        $changesAccess = array_key_exists('role', $data) || array_key_exists('active', $data) || array_key_exists('department_key', $data) || array_key_exists('access', $data);

        if ($user->id === $request->user()->id && (isset($data['role']) && $data['role'] !== $user->role || isset($data['active']) && ! $data['active'])) {
            return ApiResponse::error('SELF_CHANGE', 'You cannot change your own role or switch yourself off.', 422);
        }

        $role = $data['role'] ?? $user->role;
        $link = $this->links($request, $role, $data + ['guardian_id' => $user->guardian_id, 'department_key' => $data['department_key'] ?? $user->department_key]);
        if ($link instanceof \Illuminate\Http\JsonResponse) {
            return $link;
        }

        if (array_key_exists('access', $data) && ($denied = $this->accessAllowed($request, $role))) {
            return $denied;
        }
        $oldDept = $user->department_key;
        $user->fill(collect($data)->only(['name', 'email', 'username', 'phone', 'active'])->all() + ['role' => $role] + $link);
        $diff = null;
        if (array_key_exists('access', $data)) {
            $diff = PermissionCatalog::setTicked($user, $data['access'], $data['department_key'] ?? $oldDept);
        } elseif ($role !== 'staff' || (isset($data['department_key']) && $data['department_key'] !== $oldDept)) {
            $user->access_overrides = null;   // a new department or role starts from its own usual access
        }
        $user->save();
        if ($diff && ($diff['added'] || $diff['removed'])) {
            Audit::record('user.access_changed', $request, 'user', $user->id, $diff);
        }

        if ($changesAccess) {
            $user->tokens()->delete();   // new access applies from the next sign-in
            if (isset($data['active']) && ! $data['active']) {
                \App\Support\TrustedDevices::forgetAll($user->id);
            }
        }

        Audit::record('user.updated', $request, 'user', $user->id, ['fields' => array_keys($data)]);

        return ApiResponse::updated($this->shape($user->load('department')));
    }

    public function resetPassword(Request $request, User $user)
    {
        if ($denied = $this->guard($request, $user)) {
            return $denied;
        }

        $data = $request->validate(['password' => ['required', Password::min(12)->mixedCase()->numbers(), \App\Rules\StrongPassword::for([$user->name, $user->email, $user->username])]]);
        if ($denied = $this->stepUp($request)) {
            return $denied;
        }
        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();
        \App\Support\TrustedDevices::forgetAll($user->id);

        \App\Support\Notify::to($user->id, (int) $user->foundation_id, 'security', 'Your password was reset', 'An administrator set a new password for your account. Sign in with it and change it in Settings.', 'settings.html');
        Audit::record('user.password_reset', $request, 'user', $user->id);

        return ApiResponse::deleted('Password changed and all sessions ended.');
    }

    public function revokeTokens(Request $request, User $user)
    {
        if ($denied = $this->guard($request, $user)) {
            return $denied;
        }

        $user->tokens()->delete();
        \App\Support\TrustedDevices::forgetAll($user->id);
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
        if ($user->role === 'president_admin') {
            return ApiResponse::forbidden('The President Admin is managed by the System Admin (System, President Admin).');
        }

        return null;
    }

    /** Only the President changes what a staff account can open, and only staff accounts have it. */
    private function accessAllowed(Request $request, string $role): ?\Illuminate\Http\JsonResponse
    {
        if ($request->user()->role !== 'president_admin') {
            return ApiResponse::forbidden('Only the President Admin can change what an account can open.');
        }
        if ($role !== 'staff') {
            return ApiResponse::error('ACCESS_ONLY_STAFF', 'Choosing what an account can open only applies to staff accounts.', 422);
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

    private function normaliseUsername(Request $request): void
    {
        if ($request->has('username')) {
            $request->merge(['username' => \App\Support\Usernames::normalise($request->input('username')) ?: null]);
        }
    }

    private function rules(bool $creating, ?int $ignoreId = null): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:150'],
            'email' => [$req, 'email:rfc,filter', 'max:190', 'unique:users,email'.($ignoreId ? ",$ignoreId" : '')],
            'username' => ['nullable', 'string', 'regex:'.\App\Support\Usernames::PATTERN, 'unique:users,username'.($ignoreId ? ",$ignoreId" : '')],
            'password' => $creating ? ['required', Password::min(12)->mixedCase()->numbers(), \App\Rules\StrongPassword::for($this->avoid)] : ['prohibited'],
            'role' => [$req, 'in:'.implode(',', config('roles.assignable_by_security_admin'))],
            'department_key' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('roles.departments')))],
            'guardian_id' => ['nullable', 'integer'],
            'access' => ['sometimes', 'array'],
            'access.*' => ['string', 'in:'.implode(',', PermissionCatalog::grantable())],
            'phone' => ['nullable', 'string', 'max:40'],
            'active' => $creating ? ['prohibited'] : ['sometimes', 'boolean'],
        ];
    }
}
