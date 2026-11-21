const { chromium } = require('playwright'); const fs = require('fs'); const B = 'http://127.0.0.1:8090', SP = process.argv[2];
(async () => { const b = await chromium.launch(); const c = await b.newContext({ viewport: { width: 1440, height: 950 } }); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'system@insania.test'); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(3000);
  await p.goto(B + '/app/updates.html'); await p.waitForSelector('#autoBtn'); await p.setInputFiles('#pz', SP + '/u13.zip'); await p.setInputFiles('#ps', SP + '/u13.sig'); await p.click('#autoBtn');
  await p.waitForFunction(() => /Updated to|refused|failed/i.test(document.getElementById('found').innerText + document.getElementById('formErr').innerText), null, { timeout: 90000 });
  console.log((fs.readFileSync('/srv/apxtest/site/insania_app/BUILD', 'utf8').trim() === fs.readFileSync(SP + '/bid25', 'utf8').trim() ? 'PASS' : 'FAIL') + ' UPDATE-13 applied from the old-design Updates page');
  for (let i = 0; i < 2; i++) { await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(3000); }
  const ok = await p.evaluate(() => !!document.querySelector('.mo-hero') && !!document.querySelector('.mo-rail a.active') && !!document.querySelector('#moBuild'));
  await p.waitForTimeout(1500); const build = await p.locator('#moBuild').innerText().catch(() => '');
  console.log((ok && build === fs.readFileSync(SP + '/bid25', 'utf8').trim() ? 'PASS' : 'FAIL') + ' after reload: new design, rail, version shown on Home (' + build + ')');
  console.log((errs.length ? 'FAIL' : 'PASS') + ' no script errors'); await b.close(); })().catch(e => { console.error('ERR', e.message.split('\n')[0]); process.exit(1); });
