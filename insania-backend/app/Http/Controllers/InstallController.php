<?php

namespace App\Http\Controllers;

use App\Install\Installer;
use App\Install\InstallState;
use App\Install\Requirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallController extends Controller
{
    public function show()
    {
        if (InstallState::installed()) {
            return response()->view('install.done', ['already' => true], 404);
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
            'admin_email' => ['required', 'email', 'max:190'],
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

    public function upgradeShow()
    {
        if (! InstallState::installed()) {
            return redirect('/install');
        }
        InstallState::token();

        return view('install.upgrade', ['pending' => Installer::pending(), 'hint' => InstallState::tokenHint(), 'result' => null, 'error' => null]);
    }

    public function upgradeRun(Request $request)
    {
        if (! InstallState::installed()) {
            abort(404);
        }
        if (! InstallState::tokenMatches($request->input('token'))) {
            return response()->view('install.upgrade', ['pending' => Installer::pending(), 'hint' => InstallState::tokenHint(), 'result' => null, 'error' => 'The setup code is wrong.'], 403);
        }
        try {
            $out = Installer::upgrade();
        } catch (\Throwable $e) {
            report($e);

            return response()->view('install.upgrade', ['pending' => Installer::pending(), 'hint' => InstallState::tokenHint(), 'result' => null, 'error' => 'The upgrade failed ('.class_basename($e).'). Nothing was lost; check the database and try again.'], 500);
        }

        return view('install.upgrade', ['pending' => 0, 'hint' => InstallState::tokenHint(), 'result' => $out ?: 'Done.', 'error' => null]);
    }
}
