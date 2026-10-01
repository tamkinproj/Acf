// Starts a REAL copy of the Foundation backend for client tests: a fresh project copy, installed with
// `php artisan foundation:install` (same Installer the web wizard uses), served by PHP's built-in server.
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';

export const ADMIN = { email: 'admin@test.example', password: 'Js-Test-Pass-123' };

const freePort = () => new Promise((res) => { const s = net.createServer().listen(0, '127.0.0.1', () => { const { port } = s.address(); s.close(() => res(port)); }); });

export async function startBackend({ extraInstallArgs = [] } = {}) {
  const root = path.resolve(import.meta.dirname, '../..');
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'fdn-js-'));
  const dir = path.join(tmp, 'app');
  const skip = [/\/\.git(\/|$)/, /^\/vendor(\/|$)/, /node_modules/, /\/storage\/logs/, /\/storage\/framework\/(views|sessions|cache)/, /\/storage\/app\/(db|install|private)/, /\/\.env$/, /\/tests\/js/];
  fs.cpSync(root, dir, { recursive: true, filter: (src) => !skip.some((r) => r.test(src.replace(root, ''))) });
  fs.symlinkSync(path.join(root, 'vendor'), path.join(dir, 'vendor'));
  for (const d of ['storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/logs', 'storage/app/db', 'storage/app/install', 'storage/app/private', 'bootstrap/cache']) fs.mkdirSync(path.join(dir, d), { recursive: true });
  for (const f of fs.readdirSync(path.join(dir, 'bootstrap/cache'))) fs.rmSync(path.join(dir, 'bootstrap/cache', f));

  const port = await freePort();
  const url = `http://127.0.0.1:${port}`;
  const install = spawnSync('php', ['artisan', 'foundation:install', '--driver=sqlite', '--sqlite-name=jstest', `--app-url=${url}`, '--foundation-name=Test Foundation',
    '--admin-name=Test Admin', `--admin-email=${ADMIN.email}`, `--admin-password=${ADMIN.password}`, '--device-name=Main Office', ...extraInstallArgs], { cwd: dir, encoding: 'utf8' });
  if (install.status !== 0) throw new Error(`install failed:\n${install.stdout}\n${install.stderr}`);

  const server = spawn('php', ['-S', `127.0.0.1:${port}`, '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], {
    cwd: path.join(dir, 'public'), env: { ...process.env, PHP_CLI_SERVER_WORKERS: '4', FOUNDATION_SYNC_SETTLE_SECONDS: '0' }, stdio: 'ignore' });
  for (let i = 0; i < 60; i++) {
    try { const r = await fetch(`${url}/api/system/status`); if (r.ok) break; } catch { /* not up yet */ }
    await new Promise((r) => setTimeout(r, 250));
  }
  return { url, dir, stop() { server.kill(); fs.rmSync(tmp, { recursive: true, force: true }); } };
}
