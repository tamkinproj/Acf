const { chromium, devices } = require('playwright'); const fs = require('fs'); const { execSync } = require('child_process');
const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site', APPJS = SITE + '/public_html/app/assets/app.js';
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
const tab = async p => (await p.locator('#tabbar').innerText().catch(() => '')).replace(/\s+/g, ' ').trim();
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ ...devices['iPhone 13'] }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'admin@alinsania.test'); await p.fill('#lPass', 'Str0ng#Passw0rd'); await p.click('button[type=submit]'); await p.waitForTimeout(3000);
  ok('base build: signed in and the app draws (' + await tab(p) + ')', !!(await tab(p)));
  // 1. reproduce the phone's blank page on the BASE build: a phone without the offline copy that receives an OLD app.js
  const good = fs.readFileSync(APPJS); fs.copyFileSync(SP + '/old_app.js', APPJS);
  const c2 = await b.newContext({ ...devices['iPhone 13'], serviceWorkers: 'block' }); const q = await c2.newPage();
  await q.goto(B + '/app/login.html'); await q.evaluate(s => { for (const k in s) localStorage.setItem(k, s[k]); }, await p.evaluate(() => Object.assign({}, localStorage)));
  await q.goto(B + '/app/dashboard.html'); await q.waitForTimeout(3500);
  ok('base build + old app.js = BLANK page, as on your phone (tabbar "' + await tab(q) + '", view "' + (await q.locator('#view').innerText()).trim().slice(0, 20) + '")', !(await tab(q)));
  await q.screenshot({ path: SP + '/nb_blank_base.png' }); fs.writeFileSync(APPJS, good);
  // 2. apply the patch through the fallback page with zip + .sig, as you will
  const code = fs.readFileSync(SITE + '/insania_app/storage/app/install/token', 'utf8').trim();
  await q.goto(B + '/server/upgrade?code=' + code); await q.setInputFiles('input[name=patch]', SP + '/u5.zip'); await q.setInputFiles('input[name=signature]', SP + '/u5.sig');
  await Promise.all([q.waitForNavigation(), q.click('form[action="upgrade/patch"] button')]); console.log('   check page:', (await q.locator('body').innerText()).slice(0, 160).replace(/\n+/g, ' | '));
  await Promise.all([q.waitForNavigation(), q.click('form[action=apply] button')]); console.log('   apply page:', (await q.locator('body').innerText()).slice(0, 160).replace(/\n+/g, ' | '));
  ok('server now on the new build', fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim() === fs.readFileSync(SP + '/bid17', 'utf8').trim());
  // 3. the phone that had the OLD offline copy: reopen the app twice and it must be the new version, drawn, no errors
  for (let i = 0; i < 3; i++) { await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(2500); }
  ok('old phone now runs the new copy (' + (await p.evaluate(async () => (await caches.keys()).join(','))) + ')', (await p.evaluate(async () => (await caches.keys()).join(','))).includes(fs.readFileSync(SP + '/bid17', 'utf8').trim()));
  ok('old phone draws the app (' + await tab(p) + ')', !!(await tab(p)));
  await p.goto(B + '/app/settings.html'); await p.waitForTimeout(1500); ok('new Settings has Change password', /Change password/.test(await p.locator('#view').innerText()));
  await p.goto(B + '/app/system.html'); await p.waitForTimeout(1800); ok('new System page has the President Admin card', /President Admin/.test(await p.locator('#view').innerText()));
  // 4. after the patch, the same broken mix shows the repair box instead of a blank page, and the button fixes it
  fs.copyFileSync(SP + '/old_app.js', APPJS);
  await q.goto(B + '/app/dashboard.html'); await q.waitForTimeout(4000);
  const box = await q.locator('[role=alert] h2').innerText().catch(() => '');
  ok('broken mix now shows "' + box + '" instead of a blank page', /did not load completely/.test(box)); await q.screenshot({ path: SP + '/nb_box.png' });
  fs.writeFileSync(APPJS, good);
  await q.click('[role=alert] button'); await q.waitForTimeout(3500); ok('"Load a fresh copy" brings the app back (' + await tab(q) + ')', !!(await tab(q)));
  // 5. repair.html
  await p.goto(B + '/app/repair.html'); await p.waitForTimeout(2500); ok('repair.html clears the saved copy', await p.locator('#repairMsg').isVisible() && (await p.evaluate(async () => (await caches.keys()).length)) === 0);
  await p.click('#repairMsg a.btn-main'); await p.waitForTimeout(2500); ok('after repair the app opens still signed in (' + await tab(p) + ')', !!(await tab(p)));
  // 6. landing page and server cache headers
  const h = await (await fetch(B + '/app/assets/app.js?v=1')).headers.get('cache-control'); const h2 = await (await fetch(B + '/')).headers.get('cache-control');
  ok('app files: Cache-Control ' + h + ' | home page: ' + h2, h === 'no-cache' && h2 === 'no-cache');
  await p.goto(B + '/'); await p.waitForTimeout(1500); ok('landing page still renders', /Caring for every/.test(await p.locator('body').innerText()));
  ok('no script errors on the good pages (' + errs.length + ')', errs.length === 0);
  await b.close(); console.log(bad ? 'FAILURES ' + bad : 'NEVER-BLANK CHECKS PASSED');
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 4).join(' | ')); process.exit(1); });
