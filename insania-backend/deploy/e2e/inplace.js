const { chromium } = require('playwright'); const B = 'http://127.0.0.1:8090'; const E = process.argv[2];
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const vis = async (b, lang) => { const c = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await c.newPage(); await p.goto(B + '/'); await p.waitForTimeout(1200); return { c, p }; };
(async () => {
  const b = await chromium.launch(); const P = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await P.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  let v = await vis(b); ok(await v.p.locator('text=Edit this page').count() === 0, 'visitors never see the edit button'); await v.c.close();
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'paul@alinsania.test'); await p.fill('#lPass', 'Str0ng#Passw0rd'); await Promise.all([p.waitForNavigation({ url: /(dashboard|setup)\.html/ }), p.click('button[type=submit]')]);
  await p.goto(B + '/'); await p.waitForSelector('text=Edit this page'); ok(true, 'President signed in: the landing page offers "Edit this page"');
  await p.click('text=Edit this page'); await p.waitForSelector('.cms-bar'); await p.waitForTimeout(600);
  ok(await p.locator('.cms-ed').count() > 60, 'many texts are editable in place (' + await p.locator('.cms-ed').count() + ')');
  // edit the headline, an FAQ answer, the phone, a number
  const h = p.locator('[data-cms-key=h_t1]'); await h.click(); await p.keyboard.press('Control+A'); await p.keyboard.type('Caring in place,'); 
  const faq = p.locator('[data-cms-key=fq1a]'); await faq.scrollIntoViewIfNeeded(); await faq.evaluate(n => { n.closest('details').open = true; }); await faq.click(); await p.keyboard.press('Control+A'); await p.keyboard.type('Anyone in need of help.');
  const ph = p.locator('[data-cms=phone]'); await ph.scrollIntoViewIfNeeded(); await ph.click(); await p.keyboard.press('Control+A'); await p.keyboard.type('+63 999 000 1111');
  const num = p.locator('[data-cms-num=hero_orphans]'); await num.evaluate(n => n.scrollIntoView()); await num.click(); await p.keyboard.press('Control+A'); await p.keyboard.type('150');
  ok(/Unsaved/.test(await p.locator('.cms-bar span').innerText()), 'the toolbar says there are unsaved changes');
  // a link is edited, not followed
  const before = p.url(); await p.locator('.menu a[data-cms-key=n_about]').first().dispatchEvent('click'); ok(p.url() === before, 'clicking a menu word edits it instead of jumping');
  // logo
  const [fc] = await Promise.all([p.waitForEvent('filechooser'), p.locator('header .logo').first().click()]); await fc.setFiles(E + '/photo_a.png'); await p.waitForTimeout(1500);
  ok((await p.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--logo'))).includes('/uploads/site/'), 'logo replaced on the page');
  // hero photo from the toolbar
  const [fc2] = await Promise.all([p.waitForEvent('filechooser'), p.click('.cms-bar >> text=Main photo')]); await fc2.setFiles(E + '/photo_b.jpg'); await p.waitForTimeout(1200);
  ok((await p.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--hero-photo'))).includes('/uploads/site/'), 'main photo replaced');
  // Arabic: switch language and edit the Arabic headline
  await p.evaluate(() => window.scrollTo(0, 0)); await p.locator('#lang').click(); await p.waitForTimeout(500); await p.locator('[data-cms-key=h_t1]').click(); await p.keyboard.press('Control+A'); await p.keyboard.type('نرعى في مكانها،');
  await p.click('.cms-bar >> text=حفظ المسودة'); await p.waitForFunction(() => /Draft saved|حُفظت/.test(document.querySelector('.cms-bar span').textContent), null, { timeout: 8000 }); ok(true, 'draft saved from the page');
  v = await vis(b); ok(!/Caring in place/.test(await v.p.locator('h1').first().textContent()), 'draft saved: visitors still see the old page'); await v.c.close();
  await p.click('.cms-bar button.go'); await p.waitForFunction(() => /Published|تم النشر/.test(document.querySelector('.cms-bar span').textContent), null, { timeout: 8000 }); ok(true, 'published from the page');
  v = await vis(b); const q = v.p;
  ok(/Caring in place,/.test(await q.locator('h1').first().textContent()), 'visitors: new headline'); ok((await q.locator('[data-cms=phone]').textContent()) === '+63 999 000 1111', 'visitors: new phone');
  ok((await q.locator('[data-cms-key=fq1a]').textContent()).includes('Anyone in need of help.'), 'visitors: FAQ answer changed'); ok((await q.locator('[data-cms-num=hero_orphans]').textContent()) === '150', 'visitors: number changed');
  ok((await q.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--logo'))).includes('/uploads/site/') && (await q.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--hero-photo'))).includes('/uploads/site/'), 'visitors: new logo and main photo');
  ok(await q.locator('.cms-ed').count() === 0 && await q.locator('.cms-bar').count() === 0, 'visitors: no editing tools');
  await q.locator('#lang').click(); await q.waitForTimeout(400); ok(/نرعى في مكانها/.test(await q.locator('h1').first().textContent()), 'visitors in Arabic: Arabic headline'); ok((await q.locator('.menu a').allTextContents()).join(' ').includes('من نحن'), 'untouched texts keep the page\'s own Arabic');
  // other staff cannot edit
  const c3 = await b.newContext(); const s = await c3.newPage(); await s.goto(B + '/app/login.html'); await s.fill('#lEmail', 'sara@alinsania.test'); await s.fill('#lPass', 'Str0ng#Passw0rd'); await Promise.all([s.waitForNavigation({ url: /(dashboard|setup)\.html/ }), s.click('button[type=submit]')]);
  await s.goto(B + '/'); await s.waitForTimeout(1200); ok(await s.locator('text=Edit this page').count() === 0, 'a Security Admin does not get the edit button');
  ok(errs.length === 0 && v.p.errs === undefined, 'no JS errors (' + errs.join('; ') + ')'); await b.close(); console.log(process.exitCode ? 'IN-PLACE CHECKS FAILED' : 'IN-PLACE CHECKS PASSED');
})().catch(e => { console.error('FAILED', e.message.split('\n').slice(0, 6).join(' | ')); process.exit(1); });
