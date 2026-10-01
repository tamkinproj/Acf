// A wrong SESSION_PATH / APP_URL in .env (e.g. a typo during setup) must not stop sign-in from working.
import { chromium } from 'playwright-core';
import { startBackend, ADMIN } from '../js/backend.mjs';
import fs from 'node:fs';
const be = await startBackend();
const envFile = be.dir + '/.env';
let env = fs.readFileSync(envFile, 'utf8');
env = /SESSION_PATH=/.test(env) ? env.replace(/SESSION_PATH=.*/, 'SESSION_PATH=/acf') : env + '\nSESSION_PATH=/acf\n';
fs.writeFileSync(envFile, env);
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const page = await (await browser.newContext()).newPage();
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
await page.goto(be.url + '/'); await page.waitForSelector('[data-login]');
await page.fill('#f-email', ADMIN.email); await page.fill('#f-password', ADMIN.password); await page.click('[data-login] button[type=submit]');
await page.waitForSelector('[data-claim], [data-new]', { timeout: 10000 });
ok(await page.locator('[data-claim]').count() === 1, 'signed-in requests work (the claim option appears)');
await page.click('[data-claim]');
await page.waitForSelector('.dash', { timeout: 15000 }).then(() => ok(true, 'device claimed, dashboard opens'), () => ok(false, 'device claimed, dashboard opens'));
await browser.close(); be.stop();
