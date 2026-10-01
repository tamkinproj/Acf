<?php

namespace App\Http\Controllers\Install;

use App\Core\Access\RoleSeeder;
use App\Core\Users\PasswordPolicy;
use App\Http\Controllers\Controller;
use App\Http\Middleware\InstallerAccess;
use App\Install\InstallState;
use App\Install\Upgrader;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpgradeController extends Controller
{
    public function __construct(private InstallState $state, private Upgrader $upgrader, private TenantContext $tenant) {}

    public function tokenForm(): View
    {
        $this->state->token();

        return view('install.token', ['path' => 'storage/app/install/token', 'action' => url('/upgrade/token')]);
    }

    public function tokenSubmit(Request $request, InstallerAccess $access): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:100']]);

        return $access->attempt($request, $data['token'])
            ? redirect('/upgrade')
            : back()->withErrors(['token' => 'Invalid token, or too many attempts. Wait a few minutes and try again.']);
    }

    public function show(): View|RedirectResponse
    {
        if (! $this->upgrader->needed()) {
            return redirect('/');
        }

        return view('upgrade.show', [
            'from' => $this->upgrader->installedVersion(), 'to' => config('foundation.version'),
            'needsAdmin' => ! $this->upgrader->hasPlatformAdmin(), 'minLength' => config('foundation.auth.password_min_length'),
        ]);
    }

    public function run(Request $request, RoleSeeder $roles): View|RedirectResponse
    {
        if (! $this->upgrader->needed()) {
            return redirect('/');
        }
        $needsAdmin = ! $this->upgrader->hasPlatformAdmin();
        $admin = $needsAdmin ? $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190', \App\Core\Users\UserRules::uniqueEmail(null)],
            'admin_password' => ['required', 'string', 'confirmed', PasswordPolicy::rule()],
        ]) : null;

        try {
            $result = $this->upgrader->run();
        } catch (\Throwable $e) {
            report($e);

            return redirect('/upgrade')->withErrors(['upgrade' => 'The update stopped: '.$e->getMessage().' Nothing was deleted; you can run it again.']);
        }

        if ($admin) {
            $this->tenant->asSystem(function () use ($admin, $roles) {
                User::create(['foundation_id' => null, 'name' => $admin['name'], 'email' => strtolower($admin['email']), 'password' => $admin['admin_password'],
                    'role_id' => $roles->ensurePlatform()->getKey(), 'status' => 'active']);
            });
            $result['needs_platform_admin'] = false;
        }

        return view('upgrade.done', ['result' => $result, 'createdAdmin' => $admin !== null]);
    }
}
