const { chromium, devices } = require('playwright'); const fs = require('fs');
const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site';
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
(async () => {
  const b = await chromium.launch(); const c = await b.newContext({ ...devices['iPhone 13'] }); c.setDefaultTimeout(20000); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'system@insania.test'); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(3000);
  await p.goto(B + '/app/updates.html'); await p.waitForSelector('#ps');
  ok('BEFORE: signature picker limited to "' + await p.getAttribute('#ps', 'accept') + '" (what blocked your iPhone)', !!(await p.getAttribute('#ps', 'accept')));
  await p.setInputFiles('#pz', SP + '/u8.zip'); await p.setInputFiles('#ps', SP + '/u8.sig'); await p.click('#checkBtn');
  await p.waitForSelector('[data-act=applypatch]', { timeout: 40000 }); console.log('   check: ' + (await p.locator('#found').innerText()).slice(0, 80).replace(/\n+/g, ' | '));
  await p.click('[data-act=applypatch]'); await p.waitForFunction(() => /Updated/.test(document.getElementById('found').innerText), null, { timeout: 60000 });
  ok('zip + .sig: checked and applied, server on ' + fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim(), fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim() === fs.readFileSync(SP + '/bid20', 'utf8').trim());
  for (let i = 0; i < 2; i++) { await p.goto(B + '/app/updates.html'); await p.waitForTimeout(3000); }
  ok('AFTER: signature picker takes any file (accept=' + await p.getAttribute('#ps', 'accept') + ')', (await p.getAttribute('#ps', 'accept')) === null);
  ok('no script errors', errs.length === 0); await b.close(); console.log(bad ? 'FAILURES ' + bad : 'UPDATE CHECKS PASSED');
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 4).join(' | ')); process.exit(1); });
