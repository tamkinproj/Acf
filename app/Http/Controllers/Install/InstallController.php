<?php

namespace App\Http\Controllers\Install;

use App\Core\Settings\SettingsCatalog;
use App\Core\Users\PasswordPolicy;
use App\Http\Controllers\Controller;
use App\Http\Middleware\InstallerAccess;
use App\Install\DatabaseConfig;
use App\Install\Installer;
use App\Install\InstallState;
use App\Install\Requirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * The wizard. Answers are kept in storage/app/install/state.json (never in the session and never in
 * a form round-trip); passwords are encrypted there and wiped when installation completes.
 * Controllers here only collect and validate - the work happens in App\Install\Installer.
 */
class InstallController extends Controller
{
    private const STEPS = ['requirements', 'database', 'system', 'admin'];

    public function __construct(private InstallState $state, private Requirements $requirements) {}

    // ---- access ------------------------------------------------------------

    public function tokenForm(): View
    {
        $this->state->token();   // make sure the token file exists for the deployer to read

        return view('install.token', ['path' => 'storage/app/install/token']);
    }

    public function tokenSubmit(Request $request, InstallerAccess $access): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:100']]);

        return $access->attempt($request, $data['token'])
            ? redirect('/install')
            : back()->withErrors(['token' => 'Invalid token, or too many attempts. Wait a few minutes and try again.']);
    }

    // ---- welcome / resume ---------------------------------------------------------

    public function welcome(): View
    {
        return view('install.welcome', ['error' => $this->state->data()['error'] ?? null, 'step' => $this->nextStep()]);
    }

    // ---- 1 requirements -----------------------------------------------------

    public function requirements(): View|RedirectResponse
    {
        return view('install.requirements', ['checks' => $this->requirements->checks(), 'passes' => $this->requirements->passes(), 'current' => 'requirements']);
    }

    public function requirementsSave(): RedirectResponse
    {
        if (! $this->requirements->passes()) {
            return redirect('/install/requirements')->withErrors(['requirements' => 'Fix the failed checks, then re-check.']);
        }
        $this->state->put(['requirements' => true]);

        return redirect('/install/database');
    }

    // ---- 2 database ---------------------------------------------------------

    public function database(): View|RedirectResponse
    {
        if ($redirect = $this->require('requirements')) {
            return $redirect;
        }
        $saved = $this->state->data()['database'] ?? [];   // never includes the password

        return view('install.database', ['current' => 'database', 'db' => $saved + ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'foundation', 'username' => '', 'sqlite_name' => 'foundation'],
            'mysql' => extension_loaded('pdo_mysql'), 'sqlite' => extension_loaded('pdo_sqlite'), 'testResult' => session('testResult')]);
    }

    public function databaseSave(Request $request): RedirectResponse
    {
        if ($redirect = $this->require('requirements')) {
            return $redirect;
        }
        $in = $request->validate([
            'driver' => ['required', Rule::in(['mysql', 'sqlite'])],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:253'],
            'port' => ['required_if:driver,mysql', 'nullable', 'integer', 'between:1,65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:64'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:80'],
            'db_password' => ['nullable', 'string', 'max:200'],
            'sqlite_name' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $config = $in['driver'] === 'sqlite'
                ? DatabaseConfig::sqlite($in['sqlite_name'] ?: 'foundation')
                : DatabaseConfig::mysql($in['host'], $in['port'], $in['database'], $in['username'], $in['db_password'] ?? '');
        } catch (InvalidArgumentException $e) {
            return back()->withInput($request->except('db_password'))->withErrors(['database' => $e->getMessage()]);
        }

        $result = $config->test();
        if ($request->input('action') === 'test' || ! $result['ok']) {
            return back()->withInput($request->except('db_password'))->with('testResult', $result);
        }

        $this->state->put(['database' => $in['driver'] === 'sqlite'
            ? ['driver' => 'sqlite', 'sqlite_name' => $in['sqlite_name'] ?: 'foundation']
            : ['driver' => 'mysql', 'host' => $in['host'], 'port' => (int) $in['port'], 'database' => $in['database'], 'username' => $in['username']]]);
        $this->state->putSecret('db_password_enc', $in['db_password'] ?? '');

        return redirect('/install/system');
    }

    // ---- 3 system -----------------------------------------------------------

    public function system(): View|RedirectResponse
    {
        if ($redirect = $this->require('database')) {
            return $redirect;
        }
        $defaults = ['app_name' => 'Foundation Management System', 'app_url' => request()->root(), 'timezone' => 'Asia/Manila', 'locale' => 'en'];

        return view('install.system', ['current' => 'system', 'v' => ($this->state->data()['system'] ?? []) + $defaults,
            'timezones' => \DateTimeZone::listIdentifiers(), 'locales' => SettingsCatalog::LOCALES]);
    }

    public function systemSave(Request $request): RedirectResponse
    {
        if ($redirect = $this->require('database')) {
            return $redirect;
        }
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:120'],
            'app_url' => ['required', 'url:http,https', 'max:255'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'locale' => ['required', Rule::in(SettingsCatalog::LOCALES)],
        ]);
        $data['app_url'] = rtrim($data['app_url'], '/');
        $this->state->put(['system' => $data]);

        return redirect('/install/admin');
    }

    // ---- 4 platform administrator -------------------------------------------------------

    public function admin(): View|RedirectResponse
    {
        if ($redirect = $this->require('system')) {
            return $redirect;
        }

        return view('install.admin', ['current' => 'admin', 'v' => $this->state->data()['admin'] ?? [], 'minLength' => config('foundation.auth.password_min_length')]);
    }

    public function adminSave(Request $request): RedirectResponse
    {
        if ($redirect = $this->require('system')) {
            return $redirect;
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'admin_password' => ['required', 'string', 'confirmed', PasswordPolicy::rule()],
        ]);
        if (strtolower($data['admin_password']) === strtolower($data['email'])) {
            return back()->withInput($request->only('name', 'email'))->withErrors(['admin_password' => 'The password must not be the email address.']);
        }

        $this->state->put(['admin' => ['name' => $data['name'], 'email' => strtolower($data['email'])]]);
        $this->state->putSecret('admin_password_enc', $data['admin_password']);

        return redirect('/install/review');
    }

    // ---- review + run -------------------------------------------------------

    public function review(): View|RedirectResponse
    {
        if ($redirect = $this->require('admin')) {
            return $redirect;
        }
        $d = $this->state->data();

        return view('install.review', ['current' => 'review', 'd' => $d, 'error' => $d['error'] ?? null]);
    }

    public function run(Installer $installer): View|RedirectResponse
    {
        if ($redirect = $this->require('admin')) {
            return $redirect;
        }
        $result = $installer->run();

        if (! $result['ok']) {
            return redirect('/install/review')->withErrors(['install' => $result['error']]);
        }

        // Rendered directly in this response: /install is locked from this point on, so there is no URL to come back to.
        return view('install.complete', ['summary' => $result['summary']]);
    }

    // ---- helpers ------------------------------------------------------------

    /** Redirect to the first incomplete step up to and including $step; null when all of them are done. */
    private function require(string $step): ?RedirectResponse
    {
        $data = $this->state->data();
        foreach (self::STEPS as $s) {
            if (empty($data[$s])) {
                return redirect('/install/'.$s);
            }
            if ($s === $step) {
                break;
            }
        }

        return null;
    }

    private function nextStep(): string
    {
        $data = $this->state->data();
        foreach (self::STEPS as $s) {
            if (empty($data[$s])) {
                return $s;
            }
        }

        return 'review';
    }
}
