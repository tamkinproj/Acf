const { chromium, devices } = require('playwright'); const B = 'http://127.0.0.1:8090', out = process.argv[2]; const errs = [];
(async () => { const b = await chromium.launch();
  for (const [who, email, vp] of [['aytam', 'aytam@insania.test', 'm'], ['pres', 'president@insania.test', 'm'], ['sys', 'system@insania.test', 'm'], ['aytam', 'aytam@insania.test', 'd'], ['pres', 'president@insania.test', 'd']]) {
    const c = await b.newContext(vp === 'm' ? { ...devices['iPhone 13'], deviceScaleFactor: 2 } : { viewport: { width: 1280, height: 900 } }); const p = await c.newPage(); p.on('pageerror', e => errs.push(who + ': ' + e.message));
    await p.goto(B + '/app/login.html'); await p.fill('#lEmail', email); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(3500);
    await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(3500); await p.screenshot({ path: out + '/h_' + who + '_' + vp + '.png', fullPage: vp === 'd' ? false : true }); await c.close(); }
  console.log('errors:', errs.length ? errs.join(' | ') : 'none'); await b.close(); })();
