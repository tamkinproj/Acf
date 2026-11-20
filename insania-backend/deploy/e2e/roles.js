const { chromium } = require('playwright');
const B = 'http://127.0.0.1:8090';
const accounts = ['system', 'maintenance', 'security', 'president', 'aytam', 'finance', 'projects', 'family'];
(async () => {
  const b = await chromium.launch(); let bad = 0;
  // wrong password first
  { const c = await b.newContext(); const p = await c.newPage();
    await p.goto(B + '/app/'); await p.waitForURL(/login\.html/); await p.fill('#lEmail', 'president@insania.test'); await p.fill('#lPass', 'WrongPass#2026'); await p.click('button[type=submit]');
    await p.waitForTimeout(1200); console.log('wrong password ->', p.url().split('/').pop(), '|', await p.locator('#loginErr').innerText()); await c.close(); }
  for (const a of accounts) {
    const c = await b.newContext(); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.goto(B + '/app/login.html'); await p.fill('#lEmail', a + '@insania.test'); await p.fill('#lPass', 'DemoPass#2026');
    await Promise.all([p.waitForURL(/dashboard\.html/), p.click('button[type=submit]')]);
    await p.waitForSelector('.stat, .card'); const tabs = await p.locator('#tabbar a').allInnerTexts();
    const menu = await p.locator('.menu a').allInnerTexts(); const stats = await p.locator('.stat .lab').allInnerTexts();
    // open every allowed page and make sure it renders without errors
    const hrefs = await p.locator('.menu a').evaluateAll(l => l.map(x => x.getAttribute('href'))); const notes = [];
    for (const h of hrefs) { await p.goto(B + '/app/' + h); await p.waitForTimeout(500); const txt = await p.locator('#view').innerText(); if (/Could not load|Nothing is saved|Your role does not/.test(txt)) notes.push(h + ': ' + txt.slice(0, 60)); }
    // pages the role should NOT open
    const denied = []; for (const h of ['people.html', 'payments.html', 'users.html', 'system.html', 'audit.html']) { if (hrefs.includes(h)) continue; await p.goto(B + '/app/' + h); await p.waitForTimeout(300); const txt = await p.locator('#view').innerText(); denied.push(h.replace('.html', '') + (/does not include/.test(txt) ? '=blocked' : '=OPEN!')); if (!/does not include/.test(txt)) bad++; }
    console.log(a.padEnd(11), 'menu:', menu.join(' | '), '\n            stats:', stats.length, '| problems:', notes.length ? notes : 'none', '| other pages:', denied.join(' '), '| js errors:', errs.length || 'none');
    if (notes.length || errs.length) bad++;
    await c.close();
  }
  await b.close(); console.log(bad ? 'FAILURES: ' + bad : 'ALL ROLES OK');
})().catch(e => { console.error('FAILED', e.message); process.exit(1); });
