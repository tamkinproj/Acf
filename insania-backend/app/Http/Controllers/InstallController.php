<?php

namespace App\Http\Controllers;

use App\Install\Installer;
use App\Install\InstallState;
use App\Install\Requirements;
use App\Updates\UpdateException;
use App\Updates\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallController extends Controller
{
    public function show()
    {
        if (InstallState::installed()) {
            abort(404);                                   // once installed, this page does not exist for anyone
        }
        InstallState::token();

        return view('install.form', ['rows' => Requirements::check(), 'errors' => [], 'old' => [], 'hint' => InstallState::tokenHint(), 'sqlite' => config('install.driver') === 'sqlite']);
    }

    public function run(Request $request)
    {
        if (InstallState::installed()) {
            abort(404);
        }
        $sqlite = config('install.driver') === 'sqlite';
        $old = $request->except(['db_pass', 'admin_password', 'admin_password_confirmation', 'token']);
        $fail = fn (array $errors, int $code = 422) => response()->view('install.form', ['rows' => Requirements::check(), 'errors' => $errors, 'old' => $old, 'hint' => InstallState::tokenHint(), 'sqlite' => $sqlite], $code);

        if (! InstallState::tokenMatches($request->input('token'))) {
            return $fail(['The setup code is wrong. Open the file '.InstallState::tokenHint().' with File Manager and copy what is inside.'], 403);
        }

        $v = Validator::make($request->all(), [
            'foundation' => ['required', 'string', 'max:120'],
            'app_url' => ['required', 'url', 'max:200'],
            'cors' => ['nullable', 'string', 'max:300'],
            'db_host' => [$sqlite ? 'nullable' : 'required', 'string', 'max:120'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_name' => ['required', 'string', 'max:300'],
            'db_user' => [$sqlite ? 'nullable' : 'required', 'string', 'max:120'],
            'db_pass' => ['nullable', 'string', 'max:200'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email:rfc,filter', 'max:190'],
            'admin_password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($v->fails()) {
            return $fail($v->errors()->all());
        }
        if (! Requirements::pass()) {
            return $fail(['This server does not meet the requirements listed above yet.']);
        }

        $in = $v->validated();
        Installer::useConnection($in);
        if ($problem = Installer::databaseProblem()) {
            return $fail([$problem]);
        }

        try {
            Installer::run($in);
        } catch (\Throwable $e) {
            report($e);

            return $fail(['Installation stopped part-way. Fix the cause and press Install again; it will continue. ('.class_basename($e).')'], 500);
        }

        return view('install.done', ['already' => false, 'email' => $in['admin_email'], 'api' => rtrim($in['app_url'], '/').'/api']);
    }

    /** The database already holds an installed system but the settings file / marker are gone: restore them, change no data. */
    public function reconnect(Request $request)
    {
        if (InstallState::installed()) {
            abort(404);
        }
        $sqlite = config('install.driver') === 'sqlite';
        $old = $request->except(['db_pass', 'token']);
        $fail = fn (array $errors, int $code = 422) => response()->view('install.form', ['rows' => Requirements::check(), 'errors' => $errors, 'old' => $old, 'hint' => InstallState::tokenHint(), 'sqlite' => $sqlite], $code);

        if (! InstallState::tokenMatches($request->input('token'))) {
            return $fail(['The setup code is wrong. Open the file '.InstallState::tokenHint().' with File Manager and copy what is inside.'], 403);
        }
        $v = Validator::make($request->all(), [
            'foundation' => ['nullable', 'string', 'max:120'],
            'app_url' => ['required', 'url', 'max:200'],
            'cors' => ['nullable', 'string', 'max:300'],
            'db_host' => [$sqlite ? 'nullable' : 'required', 'string', 'max:120'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_name' => ['required', 'string', 'max:300'],
            'db_user' => [$sqlite ? 'nullable' : 'required', 'string', 'max:120'],
            'db_pass' => ['nullable', 'string', 'max:200'],
        ]);
        if ($v->fails()) {
            return $fail($v->errors()->all());
        }
        $in = $v->validated() + ['foundation' => 'Al Insania Charity Foundation Inc.'];
        $in['foundation'] = $in['foundation'] ?: 'Al Insania Charity Foundation Inc.';

        Installer::useConnection($in);
        if ($problem = Installer::reconnectProblem()) {
            return $fail([$problem]);
        }
        try {
            Installer::reconnect($in);
        } catch (\Throwable $e) {
            report($e);

            return $fail(['The settings could not be saved. Check that insania_app can be written. ('.class_basename($e).')'], 500);
        }

        return view('install.done', ['already' => false, 'reconnected' => true, 'email' => 'your existing account', 'api' => rtrim($in['app_url'], '/').'/api']);
    }

    public function upgradeShow(Request $request)
    {
        if (! InstallState::installed()) {
            return redirect()->to($request->getBaseUrl().'/install');
        }
        // Invisible to everyone who does not hold the secret code (kept in storage/app/install/token, readable in File Manager).
        if (! InstallState::tokenMatches($request->query('code'))) {
            abort(404);
        }

        return view('install.upgrade', ['pending' => Installer::pending(), 'hint' => InstallState::tokenHint(), 'result' => null, 'error' => null, 'code' => (string) $request->query('code'), 'undo' => app(UpdateService::class)->latestApplied(), 'build' => app(UpdateService::class)->currentBuild()]);
    }

    public function upgradeRun(Request $request)
    {
        if (! InstallState::installed()) {
            abort(404);
        }
        if (! InstallState::tokenMatches($request->input('token'))) {
            abort(404);
        }
        try {
            $out = Installer::upgrade();
        } catch (\Throwable $e) {
            report($e);

            return response()->view('install.upgrade', ['pending' => Installer::pending(), 'hint' => InstallState::tokenHint(), 'result' => null, 'error' => 'The upgrade failed ('.class_basename($e).'). Nothing was lost; check the database and try again.'], 500);
        }

        return view('install.upgrade', ['pending' => 0, 'hint' => InstallState::tokenHint(), 'result' => $out ?: 'Done.', 'error' => null]);
    }

    // ----- patches without the app (fallback when the app itself cannot open) -----

    private function patchGate(Request $request)
    {
        if (! InstallState::installed()) {
            abort(404);
        }
        if (! InstallState::tokenMatches($request->input('token'))) {
            abort(404);
        }

        return null;
    }

    public function patchCheck(Request $request, UpdateService $updates)
    {
        if ($deny = $this->patchGate($request)) {
            return $deny;
        }
        $token = (string) $request->input('token');
        if ($big = \App\Support\UploadLimit::refused($request, 'patch')) {
            return response()->view('install.patch', ['error' => $big, 'summary' => null, 'result' => null, 'token' => $token], 413);
        }
        if (! $request->hasFile('patch') || ! $request->hasFile('signature')) {
            return response()->view('install.patch', ['error' => 'Choose both the patch zip and its signature file.', 'summary' => null, 'result' => null, 'token' => $token], 422);
        }
        try {
            $id = $updates->store($request->file('patch')->getRealPath(), (string) file_get_contents($request->file('signature')->getRealPath()));
            $summary = $updates->inspect($id)['summary'];
        } catch (UpdateException $e) {
            if (isset($id)) {
                $updates->discard($id);
            }

            return response()->view('install.patch', ['error' => $e->getMessage(), 'summary' => null, 'result' => null, 'token' => $token], 422);
        }

        return view('install.patch', ['error' => null, 'summary' => $summary, 'result' => null, 'token' => $token]);
    }

    public function patchApply(Request $request, UpdateService $updates)
    {
        if ($deny = $this->patchGate($request)) {
            return $deny;
        }
        try {
            $entry = $updates->apply((string) $request->input('id'));
        } catch (UpdateException $e) {
            return response()->view('install.patch', ['error' => $e->getMessage(), 'summary' => null, 'result' => null, 'token' => (string) $request->input('token')], 422);
        }

        return view('install.patch', ['error' => null, 'summary' => null, 'result' => 'Updated to '.$entry['version'].' (build '.$entry['build'].').', 'token' => (string) $request->input('token')]);
    }

    public function patchUndo(Request $request, UpdateService $updates)
    {
        if ($deny = $this->patchGate($request)) {
            return $deny;
        }
        try {
            $r = $updates->undo((string) $request->input('id'));
        } catch (UpdateException $e) {
            return response()->view('install.patch', ['error' => $e->getMessage(), 'summary' => null, 'result' => null, 'token' => (string) $request->input('token')], 422);
        }

        return view('install.patch', ['error' => null, 'summary' => null, 'result' => 'Undone. Build '.$r['restored_build'].' is back.', 'token' => (string) $request->input('token')]);
    }
}
