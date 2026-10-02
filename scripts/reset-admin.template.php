<?php
// ONE-TIME administrator recovery (Platform Admins and Foundation Admins). Upload to the web folder (e.g. public_html/acr), open it once, then it deletes itself.
// Proof of ownership = the secret key below, which only someone with File Manager access can read.
// You choose the new password here; nothing is shown, mailed or stored in clear text.
const RESET_KEY = '__KEY__';

if (! hash_equals(RESET_KEY, (string) ($_GET['k'] ?? ''))) { http_response_code(404); exit; }
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$page = function (string $title, string $body) use ($esc) {
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        .'<title>'.$esc($title).'</title><style>body{font:16px/1.5 -apple-system,system-ui,sans-serif;background:#f5f5f7;color:#1d1d1f;margin:0;padding:24px 16px}'
        .'main{max-width:440px;margin:24px auto}h1{font-size:26px;margin:0 0 8px}label{display:block;font-size:13px;font-weight:600;color:#6e6e73;margin:16px 0 6px}'
        .'input[type=password],select{width:100%;min-height:46px;padding:0 12px;border:1px solid #c7c7cc;border-radius:10px;font:inherit;background:#fff}'
        .'button{margin-top:20px;width:100%;min-height:46px;border:0;border-radius:10px;background:#0071e3;color:#fff;font:600 16px inherit;cursor:pointer}'
        .'.box{background:#fff;border-radius:16px;padding:20px;margin-top:16px}.bad{background:#ffe5e3;border-radius:10px;padding:12px;margin-top:16px}.ok{background:#e3f6e8;border-radius:10px;padding:12px;margin-top:16px}'
        .'code{background:#f0f0f3;padding:2px 6px;border-radius:6px}</style><main>'.$body.'</main>';
    exit;
};

$app = null;
foreach ([__DIR__.'/../../foundation_app', __DIR__.'/../foundation_app'] as $candidate) {
    if (is_file($candidate.'/bootstrap/app.php')) { $app = realpath($candidate); break; }
}
if (! $app) { $page('Not found', '<h1>foundation_app not found</h1><div class="bad">It must be in the folder that contains <code>public_html</code>.</div>'); }
if (! is_file("$app/.env")) { $page('Not installed', '<h1>Not installed</h1><div class="bad">There is no <code>.env</code>, so this system was never installed here. Use the setup wizard.</div>'); }

require "$app/vendor/autoload.php";
$laravel = require "$app/bootstrap/app.php";
$laravel->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

use App\Core\Users\PasswordPolicy;
use App\Models\Foundation;
use App\Models\User;
use App\Tenancy\TenantContext;

// Recovery works across the whole platform, so it reads and writes outside any one foundation.
$tenant = $laravel->make(TenantContext::class);
try {
    $admins = $tenant->asSystem(function () {
        $names = Foundation::query()->pluck('name', 'id');

        return User::query()->whereHas('role', fn ($q) => $q->whereIn('key', ['platform_admin', 'foundation_admin']))
            ->orderByRaw('foundation_id is not null')->orderBy('created_at')->get(['id', 'name', 'email', 'status', 'foundation_id'])
            ->each(fn ($u) => $u->setAttribute('where', $u->foundation_id ? ($names[$u->foundation_id] ?? 'Foundation') : 'Platform'));
    });
} catch (Throwable $e) {
    $page('Database', '<h1>Cannot read the database</h1><div class="bad">Check the database settings in <code>.env</code>. ('.$esc(class_basename($e)).')</div>');
}
if ($admins->isEmpty()) { $page('No administrator', '<h1>No administrator found</h1><div class="bad">The installation never finished. Run the setup wizard with a new empty database.</div>'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (string) ($_POST['user'] ?? '');
    $user = $admins->firstWhere('id', $id);
    $pw = (string) ($_POST['password'] ?? '');
    if (! $user) {
        $error = 'Choose which account to reset.';
    } elseif ($pw !== (string) ($_POST['password2'] ?? '')) {
        $error = 'The two passwords do not match.';
    } else {
        $v = Illuminate\Support\Facades\Validator::make(['password' => $pw], ['password' => ['required', PasswordPolicy::rule()]]);
        if ($v->fails()) { $error = $v->errors()->first(); }
    }
    if ($error === '') {
        $target = $tenant->asSystem(fn () => User::query()->findOrFail($user->id));
        $tenant->asSystem(fn () => $target->forceFill(['password' => $pw, 'must_change_password' => false, 'status' => 'active'])->save());
        try { $laravel->make(App\Core\Users\SessionRevoker::class)->revokeAll($target->getKey()); } catch (Throwable) {}
        try { Illuminate\Support\Facades\RateLimiter::clear('login'); Illuminate\Support\Facades\Cache::flush(); } catch (Throwable) {}
        try { $tenant->asSystem(fn () => $laravel->make(App\Core\Audit\Auditor::class)->record('user.password_reset', 'Administrator password reset with the recovery file', 'users', $target->getKey(), null, null, $target, $target->foundation_id)); } catch (Throwable) {}
        @unlink(__FILE__);
        $page('Done', '<h1>Password changed</h1><div class="ok">You can now sign in as <b>'.$esc($target->email).'</b> with the password you just chose.</div>'
            .'<div class="box">This recovery file has <b>deleted itself</b>. If it is still listed in File Manager, delete <code>'.$esc(basename(__FILE__)).'</code> now.</div>');
    }
}

$options = '';
foreach ($admins as $a) { $options .= '<option value="'.$esc($a->id).'">'.$esc($a->where).' · '.$esc($a->name).' — '.$esc($a->email).($a->status !== 'active' ? ' (disabled)' : '').'</option>'; }
$page('Reset administrator', '<h1>Reset administrator</h1><p>Choose a new password. Other devices will be signed out.</p>'
    .($error ? '<div class="bad">'.$esc($error).'</div>' : '')
    .'<form method="post" class="box" autocomplete="off"><label for="user">Account</label><select id="user" name="user">'.$options.'</select>'
    .'<label for="p1">New password</label><input id="p1" type="password" name="password" required autocomplete="new-password">'
    .'<label for="p2">Repeat new password</label><input id="p2" type="password" name="password2" required autocomplete="new-password">'
    .'<p style="font-size:13px;color:#6e6e73">At least '.(int) config('foundation.auth.password_min_length').' characters, with letters and numbers.</p>'
    .'<button type="submit">Change password</button></form>');
