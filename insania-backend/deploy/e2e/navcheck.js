const { chromium, devices } = require('playwright'); const B = 'http://127.0.0.1:8090', out = process.argv[2];
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
(async () => { const b = await chromium.launch();
  const c = await b.newContext({ viewport: { width: 1440, height: 950 } }); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'aytam@insania.test'); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(3000);
  for (const pg of ['dashboard.html', 'people.html', 'applications.html', 'settings.html']) {
    await p.goto(B + '/app/' + pg); await p.waitForTimeout(2500);
    const r = await p.evaluate(() => {
      const rail = document.querySelector('.mo-rail'), act = document.querySelector('.mo-rail a.active'), pill = document.querySelector('.menu a.active');
      const ina = Array.from(document.querySelectorAll('.mo-rail a:not(.active) .icon, .mo-rail button .icon')).map(i => getComputedStyle(i).color);
      return { railVisible: !!rail && rail.getBoundingClientRect().width > 0, activeBg: act ? getComputedStyle(act).backgroundColor : null, activeIcon: act ? getComputedStyle(act.querySelector('.icon')).color : null,
        pillBg: pill ? getComputedStyle(pill).backgroundColor : null, inactive: Array.from(new Set(ina)), box: !!document.querySelector('[role=alert] h2') };
    });
    ok(pg + ': rail ' + r.railVisible + ', active black circle ' + r.activeBg + ' white icon ' + r.activeIcon + ', inactive icons ' + r.inactive.join('/') + ', active pill ' + r.pillBg,
      r.railVisible && r.activeBg === 'rgb(18, 18, 18)' && r.activeIcon === 'rgb(255, 255, 255)' && r.inactive.every(x => x === 'rgb(18, 18, 18)') && r.pillBg === 'rgb(18, 18, 18)' && !r.box);
  }
  await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(2500);
  await p.click('[data-act=mochart][data-i="1"]'); await p.waitForTimeout(300); ok('chart tabs switch (City)', (await p.locator('.mo-seg [aria-selected=true]').innerText()) === 'City');
  await p.locator('.mo-bar').nth(1).click(); await p.waitForTimeout(300); ok('tapping a bar moves the lime highlight and value tip', (await p.locator('.mo-bar.on').count()) === 1 && (await p.locator('.mo-bar').nth(1).getAttribute('class')).includes('on') && (await p.locator('.mo-tip').count()) === 1);
  await p.fill('.mo-search input', 'Hassan'); await p.press('.mo-search input', 'Enter'); await p.waitForURL(/people\.html\?q=Hassan/); await p.waitForTimeout(2500);
  ok('Home search opens Orphans filtered', /Amina Hassan/.test(await p.locator('#plist').innerText()) && (await p.inputValue('#q')) === 'Hassan');
  await p.click('.mo-rail [data-act=lang]'); await p.waitForTimeout(800); await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(3000);
  const rtl = await p.evaluate(() => { const r = document.querySelector('.mo-rail').getBoundingClientRect(); return { dir: document.dir, railRight: r.left > innerWidth / 2, sideways: document.documentElement.scrollWidth - innerWidth }; });
  ok('Arabic: right-to-left, rail moves to the right side, no sideways scroll', rtl.dir === 'rtl' && rtl.railRight && rtl.sideways === 0); await p.screenshot({ path: out + '/mo_ar_d.png' });
  ok('no script errors', errs.length === 0); await b.close(); console.log(bad ? 'FAILURES ' + bad : 'NAV CHECKS PASSED'); })().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 4).join(' | ')); process.exit(1); });
