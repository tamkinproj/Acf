// End-to-end check in a real Chromium against a real, freshly installed backend. Run: npm install && node conflict.mjs (screenshots land in the temp folder printed at the end).
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright-core';
import { startBackend, ADMIN } from '../js/backend.mjs';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
const OUT = path.join(os.tmpdir(), 'fdn-e2e-conflict'); fs.mkdirSync(OUT, { recursive: true });
const be = await startBackend();
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const dbf = () => be.dir + '/storage/app/db/' + fs.readdirSync(be.dir + '/storage/app/db').find((f) => f.endsWith('.sqlite'));
const sql = (q) => execFileSync('python3', ['-c', 'import sqlite3,sys;print("|".join(str(r[0]) for r in sqlite3.connect(sys.argv[1]).execute(sys.argv[2])))', dbf(), q]).toString().trim();
const logs = [];
async function open(width = 1280) {
  const ctx = await browser.newContext({ viewport: { width, height: 800 } });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => logs.push(`[pageerror] ${e.message}`));
  return { ctx, page };
}
async function login(page, claim) {
  await page.goto(be.url + '/'); await page.waitForSelector('[data-login]');
  await page.fill('#f-email', ADMIN.email); await page.fill('#f-password', ADMIN.password); await page.click('[data-login] button[type=submit]');
  if (claim) await page.click('[data-claim]');
  else { await page.fill('[data-new] #f-name', 'Second phone'); await page.click('[data-new] button[type=submit]'); }
  await page.waitForSelector('.hero'); await page.waitForTimeout(1500);
  await page.getByRole('button', { name: 'Not now' }).click();
}
const synced = (page) => page.waitForFunction(() => /^Synced/.test(document.querySelector('.sync')?.innerText || ''), null, { timeout: 20000 });
const edit = async (page, from, to) => {
  await page.goto(be.url + '/#/places'); await page.waitForSelector('.tree-row');
  await page.locator('.tree-row', { hasText: from }).locator('[data-edit]').click();
  await page.fill('#f-name', to); await page.click('.sheet button[type=submit]'); await page.waitForTimeout(500);
};
try {
  const A = await open(); const B = await open();
  await login(A.page, true);
  // seed: A creates Philippines, syncs
  await A.page.goto(be.url + '/#/places'); await A.page.click('.page-head [data-add]');
  await A.page.fill('#f-name', 'Philippines'); await A.page.selectOption('#f-level', 'country'); await A.page.click('.sheet button[type=submit]');
  await synced(A.page);
  await login(B.page, false);
  await B.page.goto(be.url + '/#/places'); await B.page.waitForSelector('.tree-row', { timeout: 20000 });
  ok(await B.page.locator('.tree-row', { hasText: 'Philippines' }).count() === 1, 'device B received the place from A');

  for (const [round, choice] of [[1, 'accept_server'], [2, 'accept_local']]) {
    const base = round === 1 ? 'Philippines' : (await sql("select name from locations")).trim();
    await A.ctx.setOffline(true);
    await edit(A.page, base, `A-edit-${round}`);
    await edit(B.page, base, `B-edit-${round}`);
    await synced(B.page); await B.page.waitForTimeout(1500);
    ok((await sql('select name from locations')) === `B-edit-${round}`, `round ${round}: B's edit is on the server`);
    await A.ctx.setOffline(false);
    await A.page.evaluate(() => dispatchEvent(new Event('online')));
    await A.page.goto(be.url + '/#/sync');
    await A.page.waitForSelector('[data-resolve]', { timeout: 25000 });
    if (round === 1) await A.page.screenshot({ path: OUT + '/01-conflict.png' });
    ok(await A.page.locator('.diff').count() >= 1, `round ${round}: conflict card shows both versions`);
    await A.page.locator(`[data-resolve$=":${choice}"]`).click();
    await A.page.waitForFunction(() => document.querySelector('.empty')?.textContent.includes('Nothing waiting') && !document.querySelector('[data-resolve]'), null, { timeout: 20000 });
    await A.page.waitForTimeout(1500);
    const final = await sql('select name from locations');
    ok(final === (choice === 'accept_server' ? `B-edit-${round}` : `A-edit-${round}`), `round ${round}: ${choice} -> server name is ${final}`);
    await A.page.goto(be.url + '/#/places'); await A.page.waitForTimeout(800);
    const shown = await A.page.locator('.tree-row .name, .tree-row b').first().innerText().catch(() => '');
    ok(shown.includes(final) || (await A.page.content()).includes(final), `round ${round}: A shows ${final}`);
    await B.page.waitForTimeout(500);
  }
} catch (e) { console.log('FAIL', e.message.split('\n')[0]); process.exitCode = 1; }
console.log(logs.join('\n') || 'no page errors');
await browser.close(); be.stop();
console.log('screenshots: ' + OUT);
