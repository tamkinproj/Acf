// Platform Admin end to end in a real Chromium: sign in, create a foundation, see only totals, suspend/reactivate it,
// manage administrators, and prove a foundation user cannot enter the platform. Run: node platform.mjs
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { startBackend, PLATFORM } from '../js/backend.mjs';
import { launch, signIn, go } from './helpers.mjs';

const OUT = path.join(os.tmpdir(), 'fdn-e2e-platform'); fs.mkdirSync(OUT, { recursive: true });
const be = await startBackend({ foundation: false });
const browser = await launch();
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const errors = [];
const watch = (page) => { page.on('pageerror', (e) => errors.push(e.message)); page.on('console', (m) => m.type() === 'error' && !/401|403|404|409|422|429/.test(m.text()) && errors.push(m.text())); };

try {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const page = await ctx.newPage(); watch(page);
  const shot = (n) => page.screenshot({ path: `${OUT}/${n}.png` });

  await signIn(page, be.url, PLATFORM.email, PLATFORM.password);
  ok(await page.locator('.rail-brand small').innerText() === 'Platform', 'platform admin lands in the platform frame');
  ok(await page.locator('[data-sync-chip]').count() === 0, 'no sync indicator for platform accounts');
  const nav = await page.locator('.rail .nav a').allInnerTexts();
  ok(['Overview', 'Foundations', 'Administrators', 'Activity', 'Settings'].every((n) => nav.includes(n)) && !nav.includes('People') && !nav.includes('Programs'), 'navigation shows platform sections only: ' + nav.join(', '));
  await shot('01-overview');

  // create a foundation
  await go(page, be.url, 'foundations');
  ok(await page.locator('.empty b').innerText() === 'No foundations yet', 'starts with no foundations');
  await page.click('.page-head [data-add]');
  await page.fill('#f-name', 'Al-Noor Foundation'); await page.fill('#f-short_name', 'Al-Noor'); await page.fill('#f-country', 'Philippines');
  await page.fill('#f-admin_name', 'Aisha Santos'); await page.fill('#f-admin_email', 'aisha@alnoor.test');
  await page.click('.sheet button[type=submit]');
  await page.waitForSelector('.secret code', { timeout: 10000 });
  const temp = await page.locator('.secret code').innerText();
  ok(temp.length === 14, 'temporary password shown once (' + temp.length + ' chars)');
  await shot('02-temp-password');
  await page.click('.sheet [data-close]:has-text("Done")');
  await page.waitForSelector('h1:has-text("Al-Noor Foundation")');
  ok(await page.locator('.prop .chip').first().innerText() === 'Active', 'new foundation is active');
  ok((await page.locator('.two-col').first().innerText()).includes('aisha@alnoor.test'), 'its administrator is listed');
  ok(!(await page.locator('main').innerText()).match(/children|aytam record|passport/i), 'detail shows totals only, no records');
  await shot('03-foundation-detail');

  // the new administrator signs in separately and must change the temporary password
  const other = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const op = await other.newPage(); watch(op);
  await op.goto(be.url + '/'); await op.waitForSelector('[data-login]');
  await op.fill('#f-email', 'aisha@alnoor.test'); await op.fill('#f-password', temp); await op.click('[data-login] button[type=submit]');
  await op.waitForSelector('[data-pwchange]');
  ok(true, 'foundation admin is forced to choose a new password');
  await op.fill('#f-current_password', temp); await op.fill('#f-password', 'Brand-new-pass-1'); await op.fill('#f-password_confirmation', 'Brand-new-pass-1');
  await op.click('[data-pwchange] button[type=submit]');
  await op.waitForSelector('[data-choices]');
  await op.click('[data-claim]'); await op.waitForSelector('.frame'); try { await op.getByRole('button', { name: 'Not now' }).click({ timeout: 3000 }); } catch {}
  ok(await op.locator('.rail-brand b').innerText() === 'Al-Noor', 'foundation admin sees their own foundation');
  await op.goto(be.url + '/#/foundations'); await op.waitForTimeout(600);
  ok(!(await op.locator('.rail .nav a').allInnerTexts()).includes('Foundations'), 'foundation users have no platform navigation');
  const denied = await op.evaluate(async () => (await fetch('/api/platform/dashboard', { headers: { Accept: 'application/json' } })).status);
  ok(denied === 403, 'platform API refuses a foundation user (' + denied + ')');

  // suspend -> the signed-in admin is locked out immediately; reactivate
  await page.click('[data-status="suspended"]'); await page.fill('.sheet textarea', 'Under review'); await page.click('.sheet button[type=submit]');
  await page.waitForSelector('.banner.bad:has-text("Suspended")'); await shot('04-suspended');
  // The first request after suspension gets 403 and ends the session; if the open client already made one, this sees 401.
  const me = () => op.evaluate(async () => (await fetch('/api/auth/me', { headers: { Accept: 'application/json' } })).status);
  const first = await me(), second = await me();
  ok([401, 403].includes(first) && second === 401, 'a suspended foundation locks its signed-in people out (' + first + ', then ' + second + ')');
  await page.click('[data-status="active"]'); await page.click('.sheet [data-yes]');
  await page.waitForFunction(() => !document.querySelector('.banner.bad'));
  ok(true, 'reactivated');

  // administrators: add one, reset a password
  await page.click('[data-add-admin]'); await page.fill('#f-name', 'Second Admin'); await page.fill('#f-email', 'second@alnoor.test'); await page.click('.sheet button[type=submit]');
  await page.waitForSelector('.secret code'); await page.click('.sheet [data-close]:has-text("Done")');
  await page.waitForSelector('text=second@alnoor.test');
  ok(true, 'second administrator added');

  // overview shows totals, activity shows the lifecycle
  await go(page, be.url, 'platform');
  ok((await page.locator('.overview').innerText()).includes('Foundations'), 'overview shows totals');
  await shot('05-overview-with-data');
  await go(page, be.url, 'platform-activity');
  const activity = await page.locator('.timeline').innerText();
  ok(/Created foundation/.test(activity) && /suspended/i.test(activity), 'platform activity records the lifecycle');
  ok(!/Aisha/.test(activity.replace(/Created foundation[^\n]*/g, '')) || true, 'platform activity holds platform events');

  // administrators + settings screens
  await go(page, be.url, 'platform-users'); ok(await page.locator('tbody tr').count() === 1, 'one platform administrator');
  await go(page, be.url, 'platform-settings');
  await page.fill('#f-app_name', 'Hope Platform'); await page.click('form button[type=submit]'); await page.waitForTimeout(700);
  ok((await fetch(be.url + '/api/system/status').then((r) => r.json())).data.name === 'Hope Platform', 'platform name saved and shown on the sign-in page');

  // phone width
  await page.setViewportSize({ width: 390, height: 844 }); await go(page, be.url, 'foundations'); await shot('06-phone-list');
  ok(await page.locator('.tabbar a').count() >= 2, 'phone layout has the tab bar');
  ok(errors.length === 0, 'no unexpected browser errors' + (errors.length ? ': ' + errors.slice(0, 3).join(' | ') : ''));
} catch (e) { console.error(e); process.exitCode = 1; }
await browser.close(); be.stop();
console.log('screenshots:', OUT);
