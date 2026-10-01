// End-to-end check in a real Chromium against a real, freshly installed backend. Run: npm install && node offline.mjs (screenshots land in the temp folder printed at the end).
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright-core';
import { startBackend, ADMIN } from '../js/backend.mjs';
import fs from 'node:fs';
const OUT = path.join(os.tmpdir(), 'fdn-e2e-offline'); fs.mkdirSync(OUT, { recursive: true });
const be = await startBackend();
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
const logs = [];
page.on('console', (m) => { if (m.type() === 'error' && !/401/.test(m.text())) logs.push(`[${m.type()}] ${m.text()}`); });
page.on('pageerror', (e) => logs.push(`[pageerror] ${e.message}`));
const shot = (n) => page.screenshot({ path: `${OUT}/${n}.png` });
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const api = (p) => page.evaluate(async (u) => (await (await fetch(u, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()), be.url + '/api' + p);
try {
  await page.goto(be.url + '/'); await page.waitForSelector('[data-login]');
  await page.fill('#f-email', ADMIN.email); await page.fill('#f-password', ADMIN.password); await page.click('[data-login] button[type=submit]');
  await page.click('[data-claim]'); await page.waitForSelector('.hero'); await page.waitForTimeout(1500);
  await page.getByRole('button', { name: 'Not now' }).click();
  await page.goto(be.url + '/#/places'); await page.waitForSelector('[data-add]');

  // ---- go offline, add places
  await ctx.setOffline(true);
  await page.click('.page-head [data-add]');
  await page.fill('#f-name', 'Philippines'); await page.selectOption('#f-level', 'country');
  await page.click('.sheet button[type=submit]'); await page.waitForSelector('.tree-row', { timeout: 5000 });
  ok(await page.locator('.tree-row', { hasText: 'Philippines' }).count() === 1, 'offline: place appears immediately');
  await page.waitForTimeout(800); await shot('01-offline-added');
  const rowAdd = page.locator('.tree-row', { hasText: 'Philippines' }).locator('[data-add]');
  if (await rowAdd.count()) { await rowAdd.first().click(); await page.fill('#f-name', 'Region XII'); await page.click('.sheet button[type=submit]'); await page.waitForTimeout(600); }
  const pill = await page.locator('.sync').first().innerText();
  ok(/offline|waiting|change/i.test(pill), `indicator says: "${pill.replace(/\n/g, ' ')}"`);
  await page.goto(be.url + '/#/sync'); await page.waitForTimeout(700); await shot('02-sync-queue-offline');
  ok(await page.locator('.list li', { hasText: 'Philippines' }).count() >= 1, 'sync center lists the queued change');

  // ---- reconnect
  await ctx.setOffline(false);
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await page.waitForFunction(() => document.querySelector('.empty')?.textContent.includes('Nothing waiting'), null, { timeout: 25000 }).catch(() => {});
  await shot('03-synced');
  const server = await api('/dashboard/summary');
  console.log('summary', JSON.stringify(server.data).slice(0, 300));
  const { execFileSync } = await import('node:child_process');
  const dbf = fs.readdirSync(be.dir + '/storage/app/db').find((f) => f.endsWith('.sqlite'));
  const names = execFileSync('python3', ['-c', `import sqlite3,sys;print(','.join(sorted(r[0] for r in sqlite3.connect(sys.argv[1]).execute('select name from locations where deleted_at is null'))))`, be.dir + '/storage/app/db/' + dbf]).toString().trim().split(',').filter(Boolean);
  ok(names.includes('Philippines'), 'server received Philippines: ' + JSON.stringify(names));
  ok(names.length >= 1, 'server has places');
  await page.goto(be.url + '/#/places'); await page.waitForTimeout(800); await shot('04-places');

  // ---- mobile
  await page.setViewportSize({ width: 390, height: 844 });
  for (const r of ['dashboard', 'places', 'sync', 'users', 'roles', 'account']) { await page.goto(`${be.url}/#/${r}`); await page.waitForTimeout(900); await shot(`m-${r}`); }
  await page.click('.page-head [data-add]').catch(() => {});
} catch (e) { console.log('FAIL', e.message); await shot('99-fail'); process.exitCode = 1; }
console.log(logs.join('\n') || 'no console problems');
await browser.close(); be.stop();
console.log('screenshots: ' + OUT);
