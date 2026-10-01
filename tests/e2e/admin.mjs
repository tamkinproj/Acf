// End-to-end check in a real Chromium against a real, freshly installed backend. Run: npm install && node admin.mjs (screenshots land in the temp folder printed at the end).
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright-core';
import { startBackend, ADMIN } from '../js/backend.mjs';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
const OUT = path.join(os.tmpdir(), 'fdn-e2e-admin'); fs.mkdirSync(OUT, { recursive: true });
const be = await startBackend();
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const dbf = () => be.dir + '/storage/app/db/' + fs.readdirSync(be.dir + '/storage/app/db').find((f) => f.endsWith('.sqlite'));
const sql = (q) => execFileSync('python3', ['-c', 'import sqlite3,sys;print("|".join(str(r[0]) for r in sqlite3.connect(sys.argv[1]).execute(sys.argv[2])))', dbf(), q]).toString().trim();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
const logs = []; page.on('pageerror', (e) => logs.push(e.message));
const shot = (n) => page.screenshot({ path: `${OUT}/${n}.png` });
const synced = () => page.waitForFunction(() => /^Synced/.test(document.querySelector('.sync')?.innerText || ''), null, { timeout: 20000 });
const go = async (r) => { await page.goto(`${be.url}/#/${r}`); await page.waitForTimeout(700); };
try {
  await page.goto(be.url + '/'); await page.waitForSelector('[data-login]');
  await page.fill('#f-email', ADMIN.email); await page.fill('#f-password', ADMIN.password); await page.click('[data-login] button[type=submit]');
  await page.click('[data-claim]'); await page.waitForSelector('.hero'); await page.waitForTimeout(1500);
  await page.getByRole('button', { name: 'Not now' }).click();

  // person: create online -> temp password sheet -> appears in list
  await go('users'); await page.click('.page-head [data-add]');
  await page.fill('#f-name', 'Amina Yusuf'); await page.fill('#f-email', 'amina@test.example');
  const roleVal = await page.locator('#f-role_id option', { hasText: 'Field Worker' }).getAttribute('value');
  await page.selectOption('#f-role_id', roleVal); await page.click('.sheet button[type=submit]');
  await page.waitForSelector('.secret code', { timeout: 10000 }); await shot('01-temp-password');
  const temp = await page.locator('.secret code').innerText();
  ok(temp.length >= 12, 'temporary password shown once (' + temp.length + ' chars)');
  await page.click('.sheet [data-close]:has-text("Done")'); await page.waitForTimeout(2500);
  ok(await page.locator('.list li', { hasText: 'Amina Yusuf' }).count() === 1, 'new person appears in list after sync');
  ok(await sql("select count(*) from users where email='amina@test.example'") === '1', 'person exists on server');

  // edit person offline (status) -> queue
  await ctx.setOffline(true);
  await page.locator('.list li', { hasText: 'Amina Yusuf' }).locator('[data-edit]').click();
  await page.fill('#f-phone', '0917 555 0100'); await page.click('.sheet button[type=submit]'); await page.waitForTimeout(500);
  await shot('02-person-pending');

  // settings offline
  await go('settings');
  await page.fill('#f-sync_auto_interval_seconds', '90'); await page.locator('#f-sync_auto_interval_seconds').blur(); await page.waitForTimeout(500);
  await ctx.setOffline(false); await page.evaluate(() => dispatchEvent(new Event('online'))); await synced(); await page.waitForTimeout(1500);
  ok(await sql("select phone from users where email='amina@test.example'") === '0917 555 0100', 'offline person edit reached server');
  ok(await sql("select value from settings where key='sync.auto_interval_seconds'") .includes('90'), 'offline setting change reached server');

  // roles: save permissions
  await go('roles'); await page.locator('.card', { hasText: 'Field Worker' }).locator('[data-open]').click();
  await shot('03-role-open');
  await page.locator('[data-perm="locations.manage"]').check(); await page.click('[data-save]'); await page.waitForTimeout(1500);
  ok((await sql("select permissions from roles where key='field_worker'")).includes('locations.manage'), 'role permission saved on server');

  // devices: register -> token once
  await go('devices'); await page.click('.page-head [data-add]'); await page.fill('#f-name', 'Field phone'); await page.click('.sheet button[type=submit]');
  await page.waitForSelector('.secret code'); ok((await page.locator('.secret code').innerText()).startsWith('fdt_'), 'device token shown'); await shot('04-token');
  await page.click('.sheet [data-close]:has-text("Done")');

  // account: set PIN, lock, unlock
  await go('account'); await page.click('[data-pin-set]');
  await page.fill('#f-pin', '482916'); await page.fill('#f-pin2, [name=pin2], [name=confirm]', '482916').catch(() => {});
  const inputs = page.locator('.sheet input'); const n = await inputs.count();
  for (let i = 0; i < n; i++) await inputs.nth(i).fill('482916');
  await page.click('.sheet button[type=submit]'); await page.waitForTimeout(1500);
  ok(await page.locator('.chip', { hasText: 'PIN on' }).count() === 1, 'PIN set');

  // unsynced work + sign out warning
  await ctx.setOffline(true);
  await go('places'); await page.click('.page-head [data-add]'); await page.fill('#f-name', 'Unsynced Place'); await page.selectOption('#f-level', 'country'); await page.click('.sheet button[type=submit]'); await page.waitForTimeout(500);
  await go('account'); await page.click('[data-signout]'); await page.waitForSelector('.scrim', { timeout: 15000 }); await shot('05-signout-warning');
  ok((await page.locator('.scrim').innerText()).includes('not reached the server'), 'sign-out warns about unsynced change');
  await page.click('.scrim button:has-text("Stay signed in")');
  await ctx.setOffline(false);
} catch (e) { console.log('FAIL', e.message.split('\n')[0]); await shot('99'); process.exitCode = 1; }
console.log(logs.join('\n') || 'no page errors');
await browser.close(); be.stop();
console.log('screenshots: ' + OUT);
