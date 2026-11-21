const { chromium, devices } = require('playwright'); const fs = require('fs');
const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site';
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
const box = async p => (await p.locator('[role=alert]').innerText({ timeout: 2000 }).catch(() => '')).replace(/\s+/g, ' ').trim();
const tab = async p => (await p.locator('#tabbar').innerText({ timeout: 2000 }).catch(() => '')).replace(/\s+/g, ' ').trim();
const brave = () => { setTimeout(function () { window.ethereum.selectedAddress = undefined; }, 0); };
(async () => {
  const b = await chromium.launch(); b.contexts; const c = await b.newContext({ ...devices['iPhone 13'] }); c.setDefaultTimeout(15000); await c.addInitScript(brave); const p = await c.newPage();
  await p.goto(B + '/app/login.html'); await p.waitForTimeout(3500);
  console.log('   your server now, sign-in page message: "' + (await box(p)).slice(0, 120) + '"');
  await p.goto(B + '/app/system.html'); await p.waitForTimeout(3500);
  const before = await box(p); console.log('   system.html message: "' + before.slice(0, 160) + '"');
  ok('reproduces your A.strings error', /A\.strings is not a function/.test(before));
  const code = fs.readFileSync(SITE + '/insania_app/storage/app/install/token', 'utf8').trim();
  await p.goto(B + '/server/upgrade?code=' + code); await p.setInputFiles('input[name=patch]', SP + '/u6.zip'); await p.setInputFiles('input[name=signature]', SP + '/u6.sig');
  await Promise.all([p.waitForNavigation(), p.click('form[action="upgrade/patch"] button')]); console.log('   check: ' + (await p.locator('body').innerText()).slice(0, 140).replace(/\n+/g, ' | '));
  await Promise.all([p.waitForNavigation(), p.click('form[action=apply] button')]); console.log('   apply: ' + (await p.locator('body').innerText()).slice(0, 80).replace(/\n+/g, ' | '));
  ok('server on build ' + fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim(), fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim() === fs.readFileSync(SP + '/bid18', 'utf8').trim());
  ok('the old app.js on the server is replaced', fs.readFileSync(SITE + '/public_html/app/assets/app.js', 'utf8').includes('strings'));
  // the same phone (it had the old offline copy) — repair.html then sign in, with the Brave wallet error on every page
  await p.goto(B + '/app/repair.html'); await p.waitForTimeout(3000); ok('repair.html done', await p.locator('#repairMsg').isVisible());
  await p.goto(B + '/app/login.html'); await p.waitForTimeout(3500); ok('sign-in page: no message (' + (await box(p)).slice(0, 40) + ')', !(await box(p)));
  await p.fill('#lEmail', 'system@insania.test'); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(4000);
  ok('signed in -> ' + p.url().split('/').pop() + ' draws: ' + await tab(p), !!(await tab(p)) && !(await box(p)));
  for (const pg of ['dashboard.html', 'system.html', 'updates.html', 'settings.html', 'setup.html']) {
    await p.goto(B + '/app/' + pg); await p.waitForTimeout(3000); const v = (await p.locator('#view').innerText({ timeout: 2000 }).catch(() => '')).slice(0, 30).replace(/\s+/g, ' ');
    ok(pg + ' draws (' + v + ')', !!(await tab(p)) && !(await box(p)) && v.length > 3);
  }
  ok('System page shows the President Admin card', /President Admin/.test(await (await p.goto(B + '/app/system.html'), p.waitForTimeout(3000), p.locator('#view').innerText())));
  await b.close(); console.log(bad ? 'FAILURES ' + bad : 'YOUR-SERVER CHECKS PASSED');
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 4).join(' | ')); process.exit(1); });
