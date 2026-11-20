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
                ['key' => 'security_admin', 'required' => true, 'done' => $has('security_admin'), 'to' => 'sheet:security_admin'],
                ['key' => 'org_settings', 'required' => false, 'done' => Setting::where('key', 'org_name')->exists(), 'to' => 'system.html'],
                ['key' => 'partner_key', 'required' => false, 'done' => ApiKey::where('foundation_id', $fid)->whereNull('revoked_at')->exists(), 'to' => 'system.html'],
            ],
            'security_admin' => [
                ['key' => 'president_admin', 'required' => true, 'done' => $has('president_admin'), 'to' => 'users.html?add=1&role=president_admin'],
                ['key' => 'staff_aytam', 'required' => false, 'done' => $has('staff', 'aytam'), 'to' => 'users.html?add=1&role=staff&dept=aytam'],
                ['key' => 'staff_finance', 'required' => false, 'done' => $has('staff', 'finance'), 'to' => 'users.html?add=1&role=staff&dept=finance'],
                ['key' => 'staff_projects', 'required' => false, 'done' => $has('staff', 'projects'), 'to' => 'users.html?add=1&role=staff&dept=projects'],
                ['key' => 'maintenance', 'required' => false, 'done' => $has('admin_maintenance'), 'to' => 'users.html?add=1&role=admin_maintenance'],
            ],
            'president_admin' => [
                ['key' => 'first_child', 'required' => true, 'done' => Child::exists(), 'to' => 'people.html?add=1'],
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

    /** The one account a System Admin may create: the first Security Admin. After that, the Security Admin runs accounts. */
    public function firstSecurityAdmin(Request $request)
    {
        $actor = $request->user();
        if (User::where('foundation_id', $actor->foundation_id)->where('role', 'security_admin')->exists()) {
            return ApiResponse::error('ALREADY_EXISTS', 'A Security Admin already exists. Ask them to manage accounts.', 422);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc,filter', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()],
        ]);

        $new = User::create($data + ['role' => 'security_admin', 'foundation_id' => $actor->foundation_id, 'active' => true]);
        Audit::record('user.created', $request, 'user', $new->id, ['role' => 'security_admin', 'first' => true]);

        return ApiResponse::created($new->toApi());
    }
}
