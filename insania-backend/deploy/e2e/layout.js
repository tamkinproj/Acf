const { chromium } = require('playwright'); const B = 'http://127.0.0.1:8090';
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const accounts = ['system', 'security', 'president', 'aytam', 'finance', 'projects', 'family', 'maintenance'];
(async () => {
  const b = await chromium.launch(); let problems = [];
  // landing page
  for (const vp of [{ width: 360, height: 740 }, { width: 1280, height: 800 }]) {
    const c = await b.newContext({ viewport: vp }); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
    const bad = []; p.on('response', r => { if (r.status() >= 400 && !/favicon/.test(r.url())) bad.push(r.status() + ' ' + r.url()); });
    await p.goto(B + '/'); await p.waitForTimeout(800);
    const over = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    const signin = await p.evaluate(() => { const a = [...document.querySelectorAll('a')].find(x => /app\/login\.html/.test(x.getAttribute('href') || '') || /login/i.test(x.textContent)); return a ? a.href : (typeof LOGIN_URL !== 'undefined' ? LOGIN_URL : null); });
    ok(over <= 1 && !errs.length && !bad.length, `landing page ${vp.width}px: no sideways scroll (${over}), no errors, no broken files ${bad.slice(0, 2).join(' ')}`);
    if (vp.width === 360) ok(!!signin, 'landing page has the staff sign-in link (' + signin + ')');
    await c.close();
  }
  for (const a of accounts) {
    for (const lang of ['en', 'ar']) {
      const c = await b.newContext({ viewport: { width: 360, height: 740 } }); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
      const bad = []; p.on('response', r => { if (r.status() >= 400) bad.push(r.status() + ' ' + new URL(r.url()).pathname); });
      await p.goto(B + '/app/login.html'); await p.evaluate(l => localStorage.setItem('acf_lang', l), lang); await p.reload();
      await p.fill('#lEmail', a + '@insania.test'); await p.fill('#lPass', 'DemoPass#2026'); await Promise.all([p.waitForNavigation({ url: /(dashboard|setup)\.html/ }), p.click('button[type=submit]')]);
      const hrefs = await p.locator('.menu a').evaluateAll(l => l.map(x => x.getAttribute('href'))); const pages = hrefs.concat(['setup.html']);
      for (const h of pages) {
        await p.goto(B + '/app/' + h); await p.waitForTimeout(700);
        const r = await p.evaluate(() => ({ over: document.documentElement.scrollWidth - window.innerWidth, dir: document.documentElement.dir, txt: document.getElementById('view').innerText.slice(0, 80), raw: /\b[a-z]+_[a-z_]+\b/.test(document.getElementById('view').innerText.replace(/[\w.+-]+@[\w.-]+/g, '')) }));
        if (r.over > 1) problems.push(`${a}/${lang} ${h}: sideways scroll ${r.over}px`);
        if (r.dir !== (lang === 'ar' ? 'rtl' : 'ltr')) problems.push(`${a}/${lang} ${h}: direction ${r.dir}`);
        if (/Could not load|Loading…|جارٍ التحميل/.test(r.txt)) problems.push(`${a}/${lang} ${h}: did not load (${r.txt})`);
      }
      const realBad = bad.filter(x => !/\/server\/api\/(setup|audit)/.test(x));
      if (errs.length) problems.push(`${a}/${lang}: JS errors ${errs.join('; ')}`);
      if (realBad.length) problems.push(`${a}/${lang}: failed requests ${realBad.join(', ')}`);
      await c.close();
    }
  }
  ok(problems.length === 0, 'every page of every role, in English and Arabic, at 360px: ' + (problems.length ? problems.join(' | ') : 'no sideways scroll, right text direction, loads, no errors'));
  await b.close();
})().catch(e => { console.error('FAILED', e.message.split('\n').slice(0, 4).join(' | ')); process.exit(1); });
