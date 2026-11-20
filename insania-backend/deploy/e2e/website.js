const { chromium } = require('playwright'); const B = 'http://127.0.0.1:8090'; const E = process.argv[2];
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const PW = 'Str0ng#Passw0rd'; const XSS = 'Well 5 < 6 & <b>bold</b> "x" <img src=x onerror="window.__xss=1">';
async function visitor(b, vp, setup) { const c = await b.newContext({ viewport: vp || { width: 390, height: 844 } }); const p = await c.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message)); if (setup) await setup(p, c); return { c, p }; }
async function headline(p) { return (await p.locator('h1').first().innerText()).replace(/\s+/g, ' '); }
(async () => {
  const b = await chromium.launch();
  const P = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await P.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'paul@alinsania.test'); await p.fill('#lPass', PW); await Promise.all([p.waitForNavigation({ url: /(dashboard|setup)\.html/ }), p.click('button[type=submit]')]);
  // the page before anything is published
  let v = await visitor(b); await v.p.goto(B + '/'); await v.p.waitForTimeout(800); const before = await headline(v.p); ok(/Caring for every orphan/.test(before), 'before publishing the landing page shows its own text: "' + before + '"');
  ok(!(await (await fetch(B + '/server/api/public/site')).json()).data.content, 'public endpoint has no content yet'); await v.c.close();

  // tab bar has Website + More on a phone
  await p.goto(B + '/app/dashboard.html'); await p.waitForSelector('#tabbar a'); const tabs = (await p.locator('#tabbar a').allInnerTexts()).map(x => x.trim());
  ok(tabs.includes('Website') && tabs.includes('More') && tabs.length === 5, 'President bottom bar: ' + tabs.join(' | '));
  await p.click('#tabbar a >> text=More'); await p.waitForSelector('#sheetRoot .item'); const more = await p.locator('#sheetRoot .item').allInnerTexts(); ok(more.some(x => /Reports/.test(x)) && more.some(x => /Settings/.test(x)) && more.some(x => /Projects/.test(x)), 'More lists the rest: ' + more.map(x => x.trim()).join(', ')); await p.click('#sheetRoot [data-act=close]');

  // ---- edit
  await p.goto(B + '/app/website.html'); await p.waitForSelector('#wsBody'); ok(/Never published/.test(await p.locator('#wsState').innerText()), 'editor opens, never published');
  await p.fill('#w_texts_h_t1_en', 'Caring for every child,'); await p.fill('#w_texts_h_t1_ar', 'نرعى كل طفل،'); await p.fill('#w_texts_h_t2_en', 'with love and trust.'); await p.fill('#w_texts_a_lead_en', 'Real about text.\nSecond line.'); await p.fill('#w_texts_a_lead_ar', 'نص عنّا الحقيقي.');
  await p.fill('#w_numbers_hero_orphans', '130'); await p.fill('#w_singles_phone', '+63 917 123 4567'); await p.fill('#w_singles_email', 'info@alinsania.com'); await p.fill('#w_singles_facebook', 'https://facebook.com/alinsania'); await p.fill('#w_texts_ld_name_en', 'Paul President'); await p.fill('#w_texts_ld_name_ar', 'بول');
  // president photo
  const [fc] = await Promise.all([p.waitForEvent('filechooser'), p.click('[data-act=photo][data-path="images.president_photo"]')]); await fc.setFiles(E + '/photo_a.png'); await p.waitForSelector('.phbox img.wsimg'); ok(true, 'president photo uploaded');
  // projects: one hidden, one with hostile text
  for (let i = 0; i < 2; i++) await p.click('[data-act=add][data-list=projects]');
  await p.fill('#w_projects_0_title_en', XSS); await p.fill('#w_projects_0_title_ar', 'بئر ماء'); await p.selectOption('[data-path="projects.0.category"]', 'water'); await p.fill('#w_projects_0_location_en', 'Zone 5'); await p.fill('#w_projects_0_year', '2025'); await p.fill('#w_projects_0_beneficiaries', '300'); await p.fill('#w_projects_0_sponsor_en', 'Dar Al Ber');
  const [fc2] = await Promise.all([p.waitForEvent('filechooser'), p.click('[data-act=photo][data-path="projects.0.photo"]')]); await fc2.setFiles(E + '/photo_b.jpg'); await p.waitForSelector('details[data-key="projects.0"] .phbox img');
  await p.fill('#w_projects_1_title_en', 'Hidden project'); await p.click('[data-act=vis][data-path="projects.1.visible"]');
  await p.click('[data-act=add][data-list=team]'); await p.fill('#w_team_0_name_en', 'Aisha Rahman'); await p.fill('#w_team_0_name_ar', 'عائشة'); await p.fill('#w_team_0_role_en', 'Teacher'); await p.fill('#w_team_0_role_ar', 'معلمة');
  const [fc3] = await Promise.all([p.waitForEvent('filechooser'), p.click('[data-act=photo][data-path="team.0.photo"]')]); await fc3.setFiles(E + '/photo_a.png'); await p.waitForSelector('details[data-key="team.0"] .phbox img');
  await p.click('[data-act=add][data-list=news]'); await p.fill('#w_news_0_title_en', 'Orphans Day'); await p.fill('#w_news_0_title_ar', 'يوم اليتيم'); await p.fill('#w_news_0_summary_en', 'We celebrated together.'); await p.fill('#w_news_0_date', '2026-11-20');
  // a mistake is shown, nothing is lost
  await p.fill('#w_projects_0_year', '20x5'); await p.click('[data-act=savedraft] >> nth=0'); await p.waitForSelector('[role=alert]'); ok(/projects\.0\.year/.test(await p.locator('[role=alert]').innerText()), 'a wrong year is refused with the field named'); ok((await p.inputValue('#w_projects_0_title_en')) === XSS, 'nothing typed was lost');
  await p.fill('#w_projects_0_year', '2025'); await p.click('[data-act=savedraft] >> nth=0'); await p.waitForFunction(() => /same|differs/.test((document.getElementById('wsDraft') || { textContent: '' }).textContent) , null, { timeout: 8000 }); ok(true, 'draft saved');

  // visitors still see nothing new
  v = await visitor(b); await v.p.goto(B + '/'); await v.p.waitForTimeout(800); ok(/Caring for every orphan/.test(await headline(v.p)), 'draft saved: visitors still see the old page'); await v.c.close();
  // preview (signed-in President only)
  const pv = await P.newPage(); await pv.goto(B + '/index.html?preview=1'); await pv.waitForTimeout(1200); ok(/Caring for every child/.test(await headline(pv)) && await pv.locator('text=Preview of the draft').count() === 1, 'preview shows the draft with a ribbon');
  v = await visitor(b); await v.p.goto(B + '/index.html?preview=1'); await v.p.waitForTimeout(1000); ok(/Caring for every orphan/.test(await headline(v.p)), 'preview with no sign-in shows only the published page'); await v.c.close(); await pv.close();

  // ---- publish
  await p.click('[data-act=publish]'); await p.waitForFunction(() => /Published version 1/.test((document.getElementById('wsState') || { innerText: '' }).innerText), null, { timeout: 10000 }); ok(true, 'published version 1');
  v = await visitor(b, { width: 390, height: 844 }); await v.p.goto(B + '/'); await v.p.waitForTimeout(1500);
  const q = v.p; ok(/Caring for every child, with love and trust\./.test(await headline(q)), 'visitors: new headline "' + await headline(q) + '"');
  ok((await q.locator('[data-cms=phone]').textContent()) === '+63 917 123 4567' && (await q.locator('[data-cms=email]').textContent()) === 'info@alinsania.com', 'visitors: new phone and email');
  ok((await q.locator('a[data-cms-href=facebook]').getAttribute('href')) === 'https://facebook.com/alinsania', 'visitors: Facebook link set');
  ok((await q.locator('[data-cms-num=hero_orphans]').innerText()) === '130', 'visitors: number 130 shown');
  ok(await q.locator('[data-cms-img=president_photo] img').count() === 1, 'visitors: president photo shown');
  const cards = await q.locator('.projgrid .proj').count(); ok(cards === 1, 'visitors: only the visible project is listed (' + cards + ')');
  const ptext = (await q.locator('.projgrid .proj').first().textContent()).replace(/\s+/g, ' '); ok(ptext.includes('Well 5 < 6 & bold "x"') && ptext.includes('Zone 5') && ptext.includes('Completed 2025') && ptext.includes('300 beneficiaries') && ptext.includes('Sponsored by Dar Al Ber'), 'visitors: project details; the server already removed the hostile tags from the title');
  ok(await q.evaluate(() => window.__xss === undefined) && await q.locator('.projgrid img[src="x"]').count() === 0, 'visitors: nothing from the hostile title ran');
  ok(await q.locator('.projgrid .proj img').count() === 1, 'visitors: project photo shown');
  ok((await q.locator('.team .member').count()) === 1 && /Aisha Rahman/.test(await q.locator('.team .member').textContent()) && await q.locator('.team .member img').count() === 1, 'visitors: team member with photo');
  ok(/Orphans Day/.test(await q.locator('#news .news').first().textContent()) && /20 November 2026/.test(await q.locator('#news .news time').first().textContent()), 'visitors: news item with formatted date');
  // filters on the new cards
  await q.locator('.fbtn[data-f=water]').click(); ok(await q.locator('.projgrid .proj:visible').count() === 1, 'filter Water: the project stays'); await q.locator('.fbtn[data-f=edu]').click(); ok(await q.locator('.projgrid .proj:visible').count() === 0, 'filter Education: hidden'); await q.locator('.fbtn[data-f=all]').click();
  // Arabic
  await q.locator('#lang').click(); await q.waitForTimeout(500); ok(/نرعى كل طفل/.test(await headline(q)), 'Arabic: headline is the Arabic text'); ok(/بئر ماء/.test(await q.locator('.projgrid .proj').first().textContent()) && /عائشة/.test(await q.locator('.team .member').textContent()) && /يوم اليتيم/.test(await q.locator('#news .news').first().textContent()), 'Arabic: project, team and news in Arabic');
  ok((await q.locator('#about .lead').first().textContent()) === 'نص عنّا الحقيقي.', 'Arabic: about text'); await q.locator('#lang').click(); await q.waitForTimeout(400); ok(/Real about text\.\s*Second line\./.test(await q.locator('#about .lead').first().textContent()), 'back to English keeps the new about text');
  const over = await q.evaluate(() => document.documentElement.scrollWidth - window.innerWidth); ok(over <= 1, 'landing page 390px: no sideways scroll (' + over + ')'); ok(v.p.errs.length === 0, 'no JS errors on the landing page (' + v.p.errs.join('; ') + ')');
  // even if hostile text were already stored (written straight into the database), the page shows it as plain text
  const { execSync } = require('child_process'); const fs = require('fs'); const SPD = process.argv[3];
  const run = sql => execSync(`mariadb -uroot -S ${SPD}/mysql/run/m.sock insania -N --raw`, { input: sql }).toString();
  const pubRaw = run('select published from site_contents limit 1;').trim(); const pj = JSON.parse(pubRaw);
  pj.projects[0].title_en = '<img src=x onerror="window.__xss=2">raw'; pj.texts.h_t2.en = '<script>window.__xss=3</script>raw2';
  run("update site_contents set published='" + JSON.stringify(pj).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "';");
  const v2 = await visitor(b); await v2.p.goto(B + '/'); await v2.p.waitForTimeout(1500);
  ok(await v2.p.evaluate(() => window.__xss === undefined), 'stored hostile markup is never executed by the landing page');
  ok((await v2.p.locator('.projgrid .proj').first().textContent()).includes('<img src=x onerror="window.__xss=2">raw') && (await v2.p.locator('h1').first().textContent()).includes('<script>window.__xss=3</script>raw2'), 'it appears as literal text');
  pj.projects[0].title_en = 'Well 5 < 6 & bold "x"'; pj.texts.h_t2.en = 'with love and trust.'; run("update site_contents set published='" + JSON.stringify(pj).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "';"); await v2.c.close();
  // saved copy: server unreachable
  await q.route('**/server/api/public/site', r => r.abort()); await q.reload(); await q.waitForTimeout(1200); ok(/Caring for every child/.test(await headline(q)), 'server unreachable: the last published copy still shows'); await v.c.close();

  // ---- edit after publishing stays in draft; versions; restore
  await p.reload(); await p.waitForSelector('#wsBody'); await p.fill('#w_texts_h_t1_en', 'A second headline,'); await p.click('[data-act=savedraft] >> nth=0'); await p.waitForFunction(() => /differs/.test((document.getElementById('wsDraft') || { textContent: '' }).textContent), null, { timeout: 8000 });
  v = await visitor(b); await v.p.goto(B + '/'); await v.p.waitForTimeout(1200); ok(/Caring for every child/.test(await headline(v.p)), 'draft edit after publishing: visitors still see version 1'); await v.c.close();
  await p.click('[data-act=publish]'); await p.waitForFunction(() => /Published version 2/.test((document.getElementById('wsState') || { innerText: '' }).innerText), null, { timeout: 10000 });
  v = await visitor(b); await v.p.goto(B + '/'); await v.p.waitForTimeout(1200); ok(/A second headline/.test(await headline(v.p)), 'published again: visitors see version 2'); await v.c.close();
  await p.reload(); await p.waitForSelector('[data-act=restore]'); await p.locator('[data-act=restore][data-v="1"]').click(); await p.waitForFunction(() => /Caring for every child/.test((document.getElementById('w_texts_h_t1_en') || { value: '' }).value), null, { timeout: 8000 }); ok(true, 'version 1 restored into the draft');
  v = await visitor(b); await v.p.goto(B + '/'); await v.p.waitForTimeout(1200); ok(/A second headline/.test(await headline(v.p)), 'restoring changes nothing for visitors until published'); await v.c.close();

  // ---- who can reach it
  for (const [email, pw, want] of [['sara@alinsania.test', PW, 403], ['admin@alinsania.test', PW, 403]]) {
    const r = await fetch(B + '/server/api/login', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email, password: pw }) }).then(x => x.json()); const t = r.data.token;
    const s = await fetch(B + '/server/api/site', { headers: { Authorization: 'Bearer ' + t, Accept: 'application/json' } }); ok(s.status === want, email + ' (not the President) gets ' + s.status + ' on the website editor');
  }
  const u = await fetch(B + '/server/api/site', { headers: { Accept: 'application/json' } }); ok(u.status === 401, 'no sign-in: 401');
  // the System Admin has no Website page in the app
  const c2 = await b.newContext(); const q2 = await c2.newPage(); await q2.goto(B + '/app/login.html'); await q2.fill('#lEmail', 'admin@alinsania.test'); await q2.fill('#lPass', PW); await Promise.all([q2.waitForNavigation({ url: /(dashboard|setup)\.html/ }), q2.click('button[type=submit]')]);
  await q2.goto(B + '/app/website.html'); await q2.waitForTimeout(600); ok(/does not include/.test(await q2.locator('#view').innerText()) && !/Website/.test(await q2.locator('.menu').innerText()), 'System Admin: no Website page, not in the menu');
  ok(errs.length === 0, 'no JS errors in the editor (' + errs.join('; ') + ')'); await b.close(); console.log(process.exitCode ? 'WEBSITE CHECKS FAILED' : 'WEBSITE CHECKS PASSED');
})().catch(e => { console.error('FAILED', e.message.split('\n').slice(0, 6).join(' | ')); process.exit(1); });
