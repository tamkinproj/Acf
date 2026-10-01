<?php
// READ-ONLY diagnostic for "signed in but every request says Authentication required".
// Shows cookie/session settings (never passwords or keys) and tests whether this browser keeps the cookie.
const CHECK_KEY = '__KEY__';
if (! hash_equals(CHECK_KEY, (string) ($_GET['k'] ?? ''))) { http_response_code(404); exit; }
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
$esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$app = null;
foreach ([__DIR__.'/../../foundation_app', __DIR__.'/../foundation_app'] as $c) { if (is_file($c.'/bootstrap/app.php')) { $app = realpath($c); break; } }
if (! $app || ! is_file("$app/.env")) { exit('foundation_app or .env not found'); }
require "$app/vendor/autoload.php";
$laravel = require "$app/bootstrap/app.php";
$laravel->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

$rows = [];
$add = function (string $k, $v, ?string $note = null) use (&$rows) { $rows[] = [$k, is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v, $note]; };
$host = $_SERVER['HTTP_HOST'] ?? '?';
$https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$cookiePath = (string) config('session.path');
$secure = (bool) config('session.secure');
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$add('This page was opened as', ($https ? 'https://' : 'http://').$host.$script);
$add('APP_URL in .env', config('app.url'));
$add('Session driver', config('session.driver'));
$add('Cookie name', config('session.cookie'));
$add('Cookie path (SESSION_PATH)', $cookiePath, str_starts_with($script, rtrim($cookiePath, '/').'/') || $cookiePath === '/' ? 'OK: covers this folder' : 'PROBLEM: this folder is outside the cookie path');
$add('Cookie "Secure" flag', $secure, $secure && ! $https ? 'PROBLEM: Secure cookie but this page is not https' : 'OK');
$add('Cookie domain', config('session.domain') ?: '(not set - good)');
$add('SameSite', config('session.same_site'));
try {
    $add('Rows in sessions table', Illuminate\Support\Facades\DB::table('sessions')->count());
    $last = Illuminate\Support\Facades\DB::table('sessions')->orderByDesc('last_activity')->first();
    $add('Newest session', $last ? (time() - $last->last_activity).' seconds ago, '.($last->user_id ? 'signed in' : 'not signed in') : 'none');
} catch (Throwable $e) { $add('sessions table', 'ERROR: '.class_basename($e)); }
$cookies = array_keys($_COOKIE);
$add('Cookies your browser sent here', $cookies ? implode(', ', $cookies) : '(none)');
$same = 0; foreach (explode(';', $_SERVER['HTTP_COOKIE'] ?? '') as $p) { if (str_starts_with(trim($p), config('session.cookie').'=')) { $same++; } }
$add('Copies of the session cookie sent', $same, $same > 1 ? 'PROBLEM: duplicate cookies (an old one with another path). Clear site data for manhaje.com.' : 'OK');

// cookie round trip: set on first visit, read on reload
$tname = 'fdn_check';
if (! isset($_GET['t'])) {
    setcookie($tname, 'ok', ['expires' => time() + 300, 'path' => $cookiePath ?: '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
}
$round = isset($_GET['t']) ? (isset($_COOKIE[$tname]) ? 'PASS: the browser kept and returned the test cookie' : 'FAIL: the browser did NOT return the test cookie - cookies are being blocked or dropped for this site') : 'Not run yet - tap the button below';
$add('Cookie round-trip test', $round);

echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Session check</title>'
    .'<style>body{font:15px/1.45 -apple-system,system-ui,sans-serif;background:#f5f5f7;color:#1d1d1f;margin:0;padding:20px 14px}main{max-width:560px;margin:auto}h1{font-size:24px}'
    .'table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}td{padding:10px 12px;border-bottom:1px solid #e5e5ea;vertical-align:top;overflow-wrap:anywhere}td:first-child{color:#6e6e73;width:42%}'
    .'.b{color:#c4160a;font-weight:600}.g{color:#1c7c37}a.btn{display:block;margin-top:16px;text-align:center;background:#0071e3;color:#fff;padding:13px;border-radius:10px;text-decoration:none;font-weight:600}</style><main><h1>Session check</h1><table>';
foreach ($rows as [$k, $v, $n]) { echo '<tr><td>'.$esc($k).'</td><td>'.$esc($v).($n ? '<br><span class="'.(str_starts_with($n, 'PROBLEM') ? 'b' : 'g').'">'.$esc($n).'</span>' : '').'</td></tr>'; }
echo '</table><a class="btn" href="?k='.$esc(CHECK_KEY).'&t=1">Run the cookie test (then take a screenshot)</a><p style="color:#6e6e73;font-size:13px">Read-only. Shows no passwords or keys. Delete this file when done.</p></main>';
