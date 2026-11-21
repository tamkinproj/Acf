const { chromium, devices } = require('playwright'); const fs = require('fs');
const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site', APPJS = SITE + '/public_html/app/assets/app.js';
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
const box = async p => (await p.locator('[role=alert] h2').innerText().catch(() => '')).trim();
const tab = async p => (await p.locator('#tabbar').innerText().catch(() => '')).replace(/\s+/g, ' ').trim();
// what Brave on iPhone does: its wallet script runs on every page and throws this error
const brave = () => { window.__fn = []; window.addEventListener('error', e => window.__fn.push(e.filename), true); setTimeout(function () { window.ethereum.selectedAddress = undefined; }, 0); setTimeout(function () { window.ethereum.selectedAddress = undefined; }, 5); };
async function phone(b) { const c = await b.newContext({ ...devices['iPhone 13'] }); await c.addInitScript(brave); const p = await c.newPage(); return { c, p }; }
(async () => {
  const b = await chromium.launch();
  let { c, p } = await phone(b);
  await p.goto(B + '/app/login.html'); await p.waitForTimeout(3500);
  ok('BEFORE the fix: Brave wallet error shows the false message on the sign-in page ("' + await box(p) + '")', /did not load/.test(await box(p)));
  console.log('   error came from file: "' + (await p.evaluate(() => window.__fn.join(','))) + '"'); await c.close();
  // apply UPDATE-6 through the fallback page
  const code = fs.readFileSync(SITE + '/insania_app/storage/app/install/token', 'utf8').trim(); ({ c, p } = await phone(b));
  await p.goto(B + '/server/upgrade?code=' + code); await p.setInputFiles('input[name=patch]', SP + '/u6.zip'); await p.setInputFiles('input[name=signature]', SP + '/u6.sig');
  await Promise.all([p.waitForNavigation(), p.click('form[action="upgrade/patch"] button')]); await Promise.all([p.waitForNavigation(), p.click('form[action=apply] button')]);
  console.log('   ' + (await p.locator('body').innerText()).slice(0, 80).replace(/\n+/g, ' | '));
  ok('server on the new build', fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim() === fs.readFileSync(SP + '/bid18', 'utf8').trim());
  // AFTER: same Brave error, no message, sign in works, app draws
  await p.goto(B + '/app/login.html'); await p.waitForTimeout(3500); ok('AFTER: sign-in page with Brave wallet error shows NO message ("' + await box(p) + '")', !(await box(p)));
  await p.fill('#lEmail', 'system@insania.test'); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(3500);
  ok('signed in: ' + p.url().split('/').pop() + ' draws (' + await tab(p) + '), no message', !!(await tab(p)) && !(await box(p)));
  for (const pg of ['dashboard.html', 'system.html', 'updates.html', 'settings.html', 'setup.html']) { await p.goto(B + '/app/' + pg); await p.waitForTimeout(3200); ok(pg + ' draws, no message', !!(await tab(p)) && !(await box(p))); }
  // a REAL broken app still gets the message, and it can be closed
  const good = fs.readFileSync(APPJS); fs.copyFileSync(SP + '/old_app.js', APPJS);
  const c2 = await b.newContext({ ...devices['iPhone 13'], serviceWorkers: 'block' }); await c2.addInitScript(brave); const q = await c2.newPage();
  await q.goto(B + '/app/login.html'); await q.waitForTimeout(3500); ok('real breakage on sign-in page still shows the message ("' + await box(q) + '")', /did not load/.test(await box(q)));
  await q.click('[role=alert] [data-close]'); ok('"Close and continue" removes it', !(await box(q)));
  fs.writeFileSync(APPJS, good); await q.reload(); await q.waitForTimeout(3500); ok('fixed files: no message', !(await box(q)));
  await b.close(); console.log(bad ? 'FAILURES ' + bad : 'BRAVE CHECKS PASSED');
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 4).join(' | ')); process.exit(1); });
