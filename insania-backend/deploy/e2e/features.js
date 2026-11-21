const { chromium, devices } = require('playwright'); const fs = require('fs');
const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site';
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
const tab = async p => (await p.locator('#tabbar').innerText({ timeout: 3000 }).catch(() => '')).replace(/\s+/g, ' ').trim();
const box = async p => (await p.locator('[role=alert] h2').innerText({ timeout: 1000 }).catch(() => '')).trim();
async function signIn(b, email) {
  const c = await b.newContext({ ...devices['iPhone 13'] }); c.setDefaultTimeout(20000); await c.addInitScript(() => { setTimeout(function () { window.ethereum.selectedAddress = undefined; }, 0); });
  const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', email); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(3500); return { c, p, errs };
}
(async () => {
  const b = await chromium.launch();
  // System Admin applies UPDATE-7 from the app's Updates page
  let { c, p, errs } = await signIn(b, 'system@insania.test');
  await p.goto(B + '/app/updates.html'); await p.waitForSelector('#autoBtn'); await p.setInputFiles('#pz', SP + '/u7.zip'); await p.setInputFiles('#ps', SP + '/u7.sig'); await p.click('#autoBtn');
  await p.waitForFunction(() => /Updated to|refused|not/i.test(document.getElementById('found').innerText + document.getElementById('formErr').innerText), null, { timeout: 60000 });
  console.log('   ' + ((await p.locator('#found').innerText()) + (await p.locator('#formErr').innerText())).slice(0, 90).replace(/\n+/g, ' | '));
  ok('server on build ' + fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim(), fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim() === fs.readFileSync(SP + '/bid19', 'utf8').trim());
  for (let i = 0; i < 2; i++) { await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(3000); }
  ok('System Admin keeps every tool: ' + await tab(p), /System/.test(await tab(p)) && /Updates/.test(await tab(p)));
  for (const pg of ['system.html', 'updates.html', 'setup.html', 'settings.html']) { await p.goto(B + '/app/' + pg); await p.waitForTimeout(2500); ok('  ' + pg + ' works', !!(await tab(p)) && !(await box(p)) && !/does not/.test(await p.locator('#view').innerText())); }
  await p.goto(B + '/app/system.html'); await p.waitForSelector('[data-act=chgpres]'); ok('  can still change the President Admin', true); await c.close();
  // every office role: only Home + Settings, a clear note, no office pages
  for (const [who, email] of [['President Admin', 'president@insania.test'], ['Security Admin', 'security@insania.test'], ['Projects staff', 'projects@insania.test'], ['Family', 'family@insania.test']]) {
    ({ c, p, errs } = await signIn(b, email)); await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(3000);
    const t = await tab(p), v = await p.locator('#view').innerText();
    ok(who + ': menu is only "' + t + '", note shown', t === 'Home Settings' && /Features are on the way/.test(v));
    let blocked = true; for (const pg of ['people.html', 'payments.html', 'projects.html', 'website.html', 'users.html', 'audit.html', 'reports.html', 'family.html']) { await p.goto(B + '/app/' + pg); await p.waitForTimeout(1500); if (!/does not|cannot|not allowed|no access/i.test(await p.locator('#view').innerText())) { blocked = false; console.log('     opened:', pg, (await p.locator('#view').innerText()).slice(0, 60)); } }
    ok(who + ': every office page is closed', blocked);
    await p.goto(B + '/app/settings.html'); await p.waitForTimeout(2000); ok(who + ': Settings has Change password', /Change password/.test(await p.locator('#view').innerText()));
    if (who === 'President Admin') { await p.goto(B + '/'); await p.waitForTimeout(2500); ok('President: no "Edit this page" button on the website', !(await p.locator('text=Edit this page').count())); }
    ok(who + ': no script errors', errs.length === 0 && !(await box(p))); await c.close();
  }
  ({ c, p } = await signIn(b, 'maintenance@insania.test')); await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(3000); ok('Admin Maintenance keeps System health: ' + await tab(p), /System/.test(await tab(p))); await c.close();
  await b.close(); console.log(bad ? 'FAILURES ' + bad : 'FEATURE-SWITCH CHECKS PASSED');
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 4).join(' | ')); process.exit(1); });
