<?php

/*
 * Entry point of the Insania server (public_html/server/index.php).
 * It finds the private insania_app folder and starts the app.
 * When something is wrong it shows a plain explanation instead of a blank page.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

function insania_stop(string $title, string $text): void
{
    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    $h = fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<meta name="robots" content="noindex"><title>Insania server</title></head>'
        .'<body style="margin:0;background:#f4f8ee;font:16px/1.55 system-ui,sans-serif;color:#17300d">'
        .'<main style="max-width:600px;margin:0 auto;padding:40px 18px"><h1 style="font-size:1.4rem">'.$h($title).'</h1>'
        .'<div style="background:#fff;border-radius:18px;padding:18px 20px;box-shadow:0 6px 24px rgba(23,48,13,.08)">'.$text.'</div></main></body></html>';
    exit;
}

if (PHP_VERSION_ID < 80100) {
    insania_stop('PHP is too old', '<p>This server runs PHP '.PHP_VERSION.'. The system needs PHP 8.1 or newer.</p>'
        .'<p>In hPanel open <b>Advanced → PHP Configuration</b>, choose <b>PHP 8.3</b>, save, then reload this page.</p>');
}

// Where insania_app may be: next to public_html (correct), or a level or two higher.
$app_dir = null;
foreach (['/../../insania_app', '/../../../insania_app', '/../../../../insania_app'] as $rel) {
    if (is_file(__DIR__.$rel.'/bootstrap/app.php')) {
        $app_dir = realpath(__DIR__.$rel);
        break;
    }
}

if ($app_dir === null) {
    if (is_dir(__DIR__.'/../insania_app')) {
        insania_stop('Move the insania_app folder', '<p>The folder <b>insania_app</b> is inside <b>public_html</b>. That is not safe: it holds private settings.</p>'
            .'<p>In File Manager, right-click <b>insania_app</b> → <b>Move</b>, and move it one level up, so it sits <b>next to</b> public_html (not inside it). Then reload this page.</p>');
    }
    insania_stop('The insania_app folder was not found', '<p>Upload <b>alinsania-FINAL-upload.zip</b> to the folder that <b>contains</b> public_html and extract it there.</p>'
        .'<p>Afterwards you should see <b>insania_app</b> and <b>public_html</b> side by side, and <b>server</b> inside public_html.</p>');
}

if (! is_file($app_dir.'/vendor/autoload.php') || ! is_file($app_dir.'/vendor/laravel/framework/src/Illuminate/Foundation/Application.php')) {
    insania_stop('Some files are missing', '<p>The folder <b>insania_app/vendor</b> is missing or incomplete. The upload or extraction probably stopped part-way.</p>'
        .'<p>Upload the zip again and extract it in the same place, choosing to replace files.</p>');
}

$not_writable = [];
foreach (['storage', 'storage/framework', 'storage/framework/views', 'storage/framework/cache', 'storage/logs', 'storage/app', 'bootstrap/cache'] as $d) {
    if (! is_dir($app_dir.'/'.$d)) {
        @mkdir($app_dir.'/'.$d, 0775, true);
    }
    if (! is_dir($app_dir.'/'.$d) || ! is_writable($app_dir.'/'.$d)) {
        $not_writable[] = 'insania_app/'.$d;
    }
}
if ($not_writable) {
    insania_stop('Folders cannot be written', '<p>The server cannot write to:</p><ul><li>'.implode('</li><li>', array_map('htmlspecialchars', $not_writable)).'</li></ul>'
        .'<p>In File Manager right-click <b>insania_app/storage</b> → <b>Permissions</b> → set <b>755</b> and tick <b>recursive</b>. Do the same for <b>insania_app/bootstrap/cache</b>.</p>');
}

// If PHP itself crashes before the app can report it, save the reason in a file the owner can open in File Manager.
$GLOBALS['insania_started'] = false;
register_shutdown_function(function () use ($app_dir) {
    $e = error_get_last();
    if ($GLOBALS['insania_started'] || ! $e || ! in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    @file_put_contents($app_dir.'/storage/logs/startup-error.log', date('c').' '.$e['message'].' in '.$e['file'].':'.$e['line']."\n", FILE_APPEND);
    insania_stop('The server stopped while starting', '<p>The reason was saved in <b>insania_app/storage/logs/startup-error.log</b>.</p>'
        .'<p>Open that file in File Manager and send its last line to whoever maintains the system.</p>');
});

if (file_exists($maintenance = $app_dir.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app_dir.'/vendor/autoload.php';

$app = require_once $app_dir.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$GLOBALS['insania_started'] = true; // from here on, the app reports its own errors (insania_app/storage/logs/laravel.log)

$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request = Request::capture())->send();
$kernel->terminate($request, $response);
