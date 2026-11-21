const { chromium, devices } = require('playwright'); const fs = require('fs'); const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site';
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
const tab = async p => (await p.locator('#tabbar').innerText({ timeout: 3000 }).catch(() => '')).replace(/\s+/g, ' ').trim();
async function signIn(b, email, opts) { const c = await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 2 }); c.setDefaultTimeout(20000); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', email); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(3500); return { c, p, errs }; }
(async () => { const b = await chromium.launch();
  let { c, p } = await signIn(b, 'system@insania.test'); await p.goto(B + '/app/updates.html'); await p.waitForSelector('#autoBtn'); await p.setInputFiles('#pz', SP + '/u10.zip'); await p.setInputFiles('#ps', SP + '/u10.sig'); await p.click('#autoBtn');
  await p.waitForFunction(() => /Updated to/.test(document.getElementById('found').innerText), null, { timeout: 60000 });
  ok('UPDATE-10 applied, server on ' + fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim(), fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim() === fs.readFileSync(SP + '/bid22', 'utf8').trim()); await c.close();
  // Aytam staff
  let errs; ({ c, p, errs } = await signIn(b, 'aytam@insania.test'));
  for (let i = 0; i < 2; i++) { await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(2500); }
  ok('Aytam menu: ' + await tab(p), /Orphans/.test(await tab(p)) && !/Payments|Projects|Accounts|System/.test(await tab(p)));
  await p.goto(B + '/app/people.html'); await p.waitForSelector('.list .item, .note'); await p.waitForTimeout(1500);
  const head = await p.locator('#view').innerText(); ok('list: title Orphans, status filters, count', /Orphans/.test(head) && /Active \(\d+\)/.test(head) && /Pending/.test(head) && /Ended/.test(head) && /\d+ orphans/.test(head));
  await p.screenshot({ path: SP + '/o1.png' });
  await p.click('[data-act=add]'); await p.waitForSelector('#addForm'); await p.screenshot({ path: SP + '/o2.png' });
  await p.click('#addForm .btn-main'); await p.waitForTimeout(500); ok('empty name refused', /required/.test(await p.locator('#f_name_enErr').innerText()));
  await p.fill('#f_name_en', 'Yusuf Rahman'); await p.fill('#f_name_ar', 'يوسف رحمن'); await p.selectOption('#f_gender', 'm'); await p.fill('#f_birth_date', '2016-04-12'); await p.fill('#f_city', 'Pagadian'); await p.fill('#f_grade', 'Grade 2'); await p.selectOption('#f_health_status', 'good');
  await p.fill('#f_mother_name', 'Aminah Rahman'); await p.fill('#f_father_death_date', '2021-01-05'); await p.fill('#f_brothers', '1'); await p.fill('#f_sisters', '2');
  await p.fill('#f_guardian_name', 'Hajar Rahman'); await p.fill('#f_guardian_relation', 'Aunt'); await p.fill('#f_guardian_phone', '09171234567'); await p.selectOption('#f_pay_type', 'cash');
  ok('bank block hidden for cash', !(await p.locator('#bankBox').isVisible()));
  await p.click('#addForm .btn-main'); await p.waitForTimeout(3000);
  ok('orphan added, form closed', (await p.locator('#addForm').count()) === 0 && /Yusuf Rahman/.test(await p.locator('#view').innerText()));
  await p.click('a.item:has-text("Yusuf Rahman")'); await p.waitForURL(/person\.html/); await p.waitForTimeout(2500);
  const prof = await p.locator('#view').innerText(); console.log('   profile:', prof.slice(0, 240).replace(/\n+/g, ' | '));
  ok('profile: name, Arabic name, ref, city, age, grade, health, Edit, Call', /Yusuf Rahman/.test(prof) && /يوسف رحمن/.test(prof) && /ACF-\d+/.test(prof) && /Pagadian/.test(prof) && /\d+ years/.test(prof) && /Grade 2/.test(prof) && /Good/.test(prof) && (await p.locator('[data-act=editprofile]').count()) === 1 && (await p.locator('a[href^="tel:"]').count()) === 1);
  ok('profile sections: family, responsible person, record', /Aminah Rahman/.test(prof) && /Hajar Rahman/.test(prof) && /Aunt/.test(prof) && /Added on/.test(prof));
  await p.screenshot({ path: SP + '/o3.png', fullPage: false });
  // edit
  await p.click('[data-act=editprofile]'); await p.waitForSelector('#editForm'); ok('edit form is filled in', (await p.inputValue('#f_name_en')) === 'Yusuf Rahman' && (await p.inputValue('#f_grade')) === 'Grade 2' && (await p.inputValue('#f_guardian_phone')) === '09171234567' && (await p.inputValue('#f_status')) === 'active');
  ok('edit form has no payment/bank block', (await p.locator('#f_pay_type').count()) === 0);
  await p.fill('#f_grade', 'Grade 3'); await p.fill('#f_city', 'Zamboanga'); await p.selectOption('#f_status', 'ended'); await p.selectOption('#f_health_status', 'needs_followup'); await p.fill('#f_guardian_phone', '09179999999');
  await p.click('#editForm .btn-main'); await p.waitForTimeout(3000);
  const after = await p.locator('#view').innerText(); ok('edit saved: Grade 3, Zamboanga, Ended, follow-up, new phone', /Grade 3/.test(after) && /Zamboanga/.test(after) && /Ended/.test(after) && /Needs follow-up/.test(after) && (await p.locator('a[href="tel:09179999999"]').count()) === 1);
  await p.goto(B + '/app/people.html'); await p.waitForTimeout(2500); await p.click('[data-act=tab][data-v=active]'); await p.waitForTimeout(500); ok('filter Active hides the now-Ended orphan', !/Yusuf Rahman/.test(await p.locator('#plist').innerText()));
  await p.click('[data-act=tab][data-v=ended]'); await p.waitForTimeout(500); ok('filter Ended shows it', /Yusuf Rahman/.test(await p.locator('#plist').innerText()));
  await p.fill('#q', 'Hajar'); await p.waitForTimeout(400); ok('search by guardian name', /Yusuf Rahman/.test(await p.locator('#plist').innerText()));
  ok('no script errors (Aytam)', errs.length === 0); await c.close();
  // everyone else sees nothing of it
  for (const [who, email] of [['President', 'president@insania.test'], ['Finance staff', 'finance@insania.test'], ['Projects staff', 'projects@insania.test'], ['Security Admin', 'security@insania.test'], ['System Admin', 'system@insania.test']]) {
    ({ c, p, errs } = await signIn(b, email)); for (let i = 0; i < 2; i++) { await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(2200); }
    const m = await tab(p); await p.goto(B + '/app/people.html'); await p.waitForTimeout(1800); await p.goto(B + '/app/person.html?id=1'); await p.waitForTimeout(1800);
    ok(who + ': no Orphans in the menu (' + m + '), profile closed', !/Orphans/.test(m) && !/Yusuf|ACF-/.test(await p.locator('body').innerText())); await c.close(); }
  await b.close(); console.log(bad ? 'FAILURES ' + bad : 'ORPHAN CHECKS PASSED'); })().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 5).join(' | ')); process.exit(1); });
