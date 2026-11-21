<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Child;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * "Getting started": what each role should do first, worked out from what already exists.
 * Counts only, never records: a technical role gets steps about accounts and settings, never about children or money.
 */
class SetupController extends Controller
{
    public function steps(Request $request)
    {
        $user = $request->user();
        $fid = $user->foundation_id;
        $has = fn (string $role, ?string $dept = null) => User::where('foundation_id', $fid)->where('role', $role)->where('active', true)
            ->when($dept, fn ($q) => $q->whereHas('department', fn ($d) => $d->where('key', $dept)))->exists();

        $steps = match ($user->role) {
            'system_admin' => [
                ['key' => 'president_admin', 'required' => true, 'done' => $has('president_admin'), 'to' => 'sheet:president_admin'],
                ['key' => 'org_settings', 'required' => false, 'done' => Setting::where('key', 'org_name')->exists(), 'to' => 'system.html'],
                ['key' => 'partner_key', 'required' => false, 'done' => ApiKey::where('foundation_id', $fid)->whereNull('revoked_at')->exists(), 'to' => 'system.html'],
            ],
            'security_admin' => [
                ['key' => 'staff_aytam', 'required' => false, 'done' => $has('staff', 'aytam'), 'to' => 'users.html?add=1&role=staff&dept=aytam'],
                ['key' => 'staff_finance', 'required' => false, 'done' => $has('staff', 'finance'), 'to' => 'users.html?add=1&role=staff&dept=finance'],
                ['key' => 'staff_projects', 'required' => false, 'done' => $has('staff', 'projects'), 'to' => 'users.html?add=1&role=staff&dept=projects'],
                ['key' => 'maintenance', 'required' => false, 'done' => $has('admin_maintenance'), 'to' => 'users.html?add=1&role=admin_maintenance'],
            ],
            'president_admin' => [
                ['key' => 'first_child', 'required' => true, 'done' => Child::exists(), 'to' => 'people.html?add=1'],
                ['key' => 'staff_aytam', 'required' => false, 'done' => $has('staff', 'aytam'), 'to' => 'users.html?add=1&role=staff&dept=aytam'],
                ['key' => 'staff_finance', 'required' => false, 'done' => $has('staff', 'finance'), 'to' => 'users.html?add=1&role=staff&dept=finance'],
                ['key' => 'staff_projects', 'required' => false, 'done' => $has('staff', 'projects'), 'to' => 'users.html?add=1&role=staff&dept=projects'],
                ['key' => 'security_admin', 'required' => false, 'done' => $has('security_admin'), 'to' => 'users.html?add=1&role=security_admin'],
                ['key' => 'maintenance', 'required' => false, 'done' => $has('admin_maintenance'), 'to' => 'users.html?add=1&role=admin_maintenance'],
                ['key' => 'first_publish', 'required' => false, 'done' => \App\Models\SiteContent::where('version', '>', 0)->exists(), 'to' => 'website.html'],
                ['key' => 'first_project', 'required' => false, 'done' => Project::exists(), 'to' => 'projects.html'],
            ],
            default => [],
        };

        $required = array_filter($steps, fn ($s) => $s['required']);

        return ApiResponse::ok([
            'steps' => $steps,
            'done' => count(array_filter($steps, fn ($s) => $s['done'])),
            'total' => count($steps),
            'required_left' => count(array_filter($required, fn ($s) => ! $s['done'])),
        ]);
    }

    /** The first President Admin, who then runs every office feature (and the public website content). */
    public function firstPresidentAdmin(Request $request)
    {
        return $this->firstAccount($request, 'president_admin');
    }

    /** The current President Admin (name, email, state only: nothing from the office). */
    public function president(Request $request)
    {
        $u = User::where('foundation_id', $request->user()->foundation_id)->where('role', 'president_admin')->first();

        return ApiResponse::ok($u ? ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'active' => (bool) $u->active] : null);
    }

    /**
     * The System Admin changes who the President Admin is, or the President's sign-in details.
     * Same account, so every record and the activity log stay; the old person's sessions end at once.
     * The System Admin must type their own password to confirm.
     */
    public function changePresident(Request $request)
    {
        $actor = $request->user();
        $u = User::where('foundation_id', $actor->foundation_id)->where('role', 'president_admin')->first();
        if (! $u) {
            return ApiResponse::error('NOT_FOUND', 'There is no President Admin yet. Create one first.', 404);
        }
        $data = $request->validate([
            'admin_password' => ['required', 'string', 'max:200'],
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email:rfc,filter', 'max:190', Rule::unique('users', 'email')->ignore($u->id)],
            'password' => ['sometimes', 'string', 'max:200', Password::min(12)->mixedCase()->numbers()],
            'active' => ['sometimes', 'boolean'],
        ]);
        if (! Hash::check($data['admin_password'], $actor->password)) {
            Audit::record('president.change_denied', $request, 'user', $u->id);

            return ApiResponse::error('WRONG_PASSWORD', 'Your own password is not right.', 422, ['admin_password' => ['Your own password is not right.']]);
        }
        unset($data['admin_password']);
        if (! $data) {
            return ApiResponse::error('VALIDATION_ERROR', 'Nothing to change.', 422);
        }

        $changed = array_keys($data);
        $u->update($data);
        if (isset($data['password']) || isset($data['email']) || (array_key_exists('active', $data) && ! $data['active'])) {
            $u->tokens()->delete();
        }
        Audit::record('president.changed', $request, 'user', $u->id, ['fields' => $changed]);

        return ApiResponse::ok(['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'active' => (bool) $u->active]);
    }

    /** The only account a System Admin may create: the first President Admin, who then creates every other role. */
    private function firstAccount(Request $request, string $role)
    {
        $actor = $request->user();
        if (User::where('foundation_id', $actor->foundation_id)->where('role', $role)->exists()) {
            return ApiResponse::error('ALREADY_EXISTS', 'This role already has an account. The Security Admin manages accounts from now on.', 422);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc,filter', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()],
        ]);

        $new = User::create($data + ['role' => $role, 'foundation_id' => $actor->foundation_id, 'active' => true]);
        Audit::record('user.created', $request, 'user', $new->id, ['role' => $role, 'first' => true]);

        return ApiResponse::created($new->toApi());
    }
}
