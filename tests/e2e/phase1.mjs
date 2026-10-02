// Phase 1 journey in a real browser, on the demo foundation: a Mushrif publishes the registration link, an applicant (no account)
// fills the public form, the Mushrif reviews and approves, the child gets a permanent Aytam ID, and a Field Worker still sees
// only the children assigned to them. Run: node phase1.mjs
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { startBackend, DEMO } from '../js/backend.mjs';
import { launch, signIn } from './helpers.mjs';

const OUT = path.join(os.tmpdir(), 'fdn-e2e-phase1'); fs.mkdirSync(OUT, { recursive: true });
const be = await startBackend({ demo: true });
const browser = await launch();
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const errors = [];
const watch = (page, who) => {
  page.on('pageerror', (e) => errors.push(`${who}: ${e.message}`));
  page.on('console', (m) => m.type() === 'error' && !/40[0-9]|42[0-9]|429/.test(m.text()) && errors.push(`${who}: ${m.text()}`));
  page.on('response', (r) => r.status() >= 500 && errors.push(`${who}: ${r.status()} ${r.url()}`));
};
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==', 'base64');
const PDF = Buffer.from('%PDF-1.4\n' + 'x'.repeat(1500) + '\n%%EOF');

try {
  // --- the Mushrif opens the program and finds the published link
  const mctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const m = await mctx.newPage(); watch(m, 'mushrif');
  await signIn(m, be.url, DEMO.accounts.mushrif, DEMO.password, { deviceToken: be.deviceTokens[1] });
  await m.goto(`${be.url}/#/programs`); await m.waitForTimeout(800);
  const programHref = await m.evaluate(() => [...document.querySelectorAll('a[href*="#/programs/"]')].map((a) => a.getAttribute('href'))[0]);
  ok(!!programHref, 'the Mushrif sees the Aytam program');
  await m.goto(`${be.url}/${programHref}/registration`); await m.waitForTimeout(800);
  await m.locator('main nav.segmented a', { hasText: 'Forms' }).click(); await m.waitForTimeout(600);
  const link = await m.evaluate(() => [...document.querySelectorAll('[data-copy]')].map((b) => b.dataset.copy)[0]);
  ok(/\/apply\/[a-z0-9]{40}$/.test(link || ''), 'a published registration link is shown: ' + (link || '').replace(/[a-z0-9]{40}$/, '…'));
  await m.screenshot({ path: `${OUT}/01-forms.png` });

  // --- an applicant with no account fills it in
  const actx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const a = await actx.newPage(); watch(a, 'applicant');
  await a.goto(link); await a.waitForSelector('form');
  ok(await a.locator('input[type=password]').count() === 0, 'the public form asks for no account');
  await a.evaluate(() => {
    const set = (id, v) => { const el = document.getElementById(id); if (el) { el.value = v; } };
    set('q_first_name', 'Zaid'); set('q_last_name', 'Rahimi'); set('q_date_of_birth', '2013-05-09'); set('q_gender', 'male');
    set('q_guardian_name', 'Uncle Hamza'); set('q_guardian_phone', '0917 555 0100');
    for (const p of ['country', 'province', 'city', 'barangay']) set('q_address_' + p, p === 'country' ? 'Philippines' : 'Maguindanao');
    set('q_address_address_detail', 'Block 2');
    document.querySelectorAll('select[required], select').forEach((s) => { if (!s.value && s.options.length > 1 && s.closest('.q.required, [data-required]')) s.selectedIndex = 1; });
  });
  // fill every other required text/select the standard form insists on, whatever it is called
  const required = await a.locator('[required]').evaluateAll((els) => els.map((e) => ({ id: e.id, tag: e.tagName, type: e.type, value: e.value })));
  for (const r of required) {
    if (r.type === 'file' || r.value) continue;
    if (r.tag === 'SELECT') await a.locator('#' + r.id).selectOption({ index: 1 });
    else if (r.type === 'date') await a.locator('#' + r.id).fill('2013-05-09');
    else if (r.type === 'email') await a.locator('#' + r.id).fill('hamza@example.test');
    else if (r.type === 'number') await a.locator('#' + r.id).fill('3');
    else await a.locator('#' + r.id).fill('Uncle');
  }
  await a.setInputFiles('#q_photo', { name: 'zaid.png', mimeType: 'image/png', buffer: PNG });
  await a.setInputFiles('#q_birth_certificate', { name: 'birth.pdf', mimeType: 'application/pdf', buffer: PDF });
  await a.screenshot({ path: `${OUT}/02-public-form.png`, fullPage: true });
  await a.click('button[type=submit]'); await a.waitForLoadState('load');
  const done = await a.locator('body').innerText();
  const reference = (done.match(/REG-[A-Z0-9]+/) || [])[0];
  ok(!!reference, 'the applicant gets a reference: ' + reference);
  ok(/review/i.test(done), 'the confirmation says it will be reviewed');
  await a.screenshot({ path: `${OUT}/03-submitted.png` });

  // --- the Mushrif reviews and approves
  await m.goto(`${be.url}/${programHref}/registration`); await m.waitForTimeout(900);
  const row = m.locator(`a:has-text("${reference}")`).first();
  ok(await row.count() === 1, 'the submission is waiting in the review list');
  await row.click(); await m.waitForSelector('[data-approve]');
  ok((await m.locator('main').innerText()).includes('Zaid'), 'the review shows what the applicant entered');
  await m.screenshot({ path: `${OUT}/04-review.png`, fullPage: true });
  await m.click('[data-approve]');
  // A dialog may ask to confirm; either way the permanent ID is what we wait for.
  const confirm = m.locator('.sheet button[type=submit], .sheet [data-yes]').first();
  if (await confirm.isVisible({ timeout: 1500 }).catch(() => false)) await confirm.click();
  await m.waitForSelector('text=/AYT-0000\\d\\d/', { timeout: 10000 });
  const code = (await m.locator('body').innerText()).match(/AYT-\d{6}/)[0];
  ok(code === 'AYT-000009', 'approval gives the permanent Aytam ID ' + code);
  await m.screenshot({ path: `${OUT}/05-approved.png` });

  // --- the child is in the records, with documents; the worker still sees only their own
  await m.goto(`${be.url}/${programHref}/children`); await m.waitForTimeout(900);
  ok((await m.locator('main').innerText()).includes('Zaid Rahimi'), 'the new child is in the Children list');
  const wctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const w = await wctx.newPage(); watch(w, 'worker');
  await signIn(w, be.url, DEMO.accounts.worker, DEMO.password, { deviceToken: be.deviceTokens[2] });
  await w.goto(`${be.url}/${programHref}/children`); await w.waitForTimeout(900);
  const wtxt = await w.locator('main').innerText();
  ok(!wtxt.includes('Zaid Rahimi'), 'the Field Worker does not see a child who is not assigned to them');
  ok((await w.evaluate(async () => (await fetch('/api/users', { headers: { Accept: 'application/json' } })).status)) === 403, 'the Field Worker cannot list users');
  await w.screenshot({ path: `${OUT}/06-worker.png` });

  // --- the audit trail (Foundation Admin) shows the steps, without the applicant's contact details
  const actxx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const ad = await actxx.newPage(); watch(ad, 'admin');
  await signIn(ad, be.url, DEMO.accounts.admin, DEMO.password, { deviceToken: be.deviceTokens[0] });
  await ad.goto(`${be.url}/#/activity`); await ad.waitForTimeout(1200);
  const log = await ad.locator('main').innerText();
  ok(/registration|Registration/.test(log) && /approved|Approved/.test(log), 'the activity log shows the registration being approved');
  ok(!log.includes('0917 555 0100') && !log.includes('Block 2'), 'the activity log holds no phone numbers or addresses');
  ok(errors.length === 0, 'no unexpected browser errors' + (errors.length ? ': ' + errors.slice(0, 3).join(' | ') : ''));
} catch (e) { console.error(e); process.exitCode = 1; }
await browser.close(); be.stop();
console.log('screenshots:', OUT);
