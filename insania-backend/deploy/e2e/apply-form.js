const { chromium, devices } = require('playwright'); const fs = require('fs'); const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site';
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
const tab = async p => (await p.locator('#tabbar').innerText({ timeout: 3000 }).catch(() => '')).replace(/\s+/g, ' ').trim();
async function signIn(b, email, vp) { const c = await b.newContext(vp === 'd' ? { viewport: { width: 1280, height: 900 } } : { ...devices['iPhone 13'], deviceScaleFactor: 2 }); c.setDefaultTimeout(20000); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', email); await p.fill('#lPass', 'DemoPass#2026'); await p.click('button[type=submit]'); await p.waitForTimeout(3500); return { c, p, errs }; }
(async () => { const b = await chromium.launch();
  // 1. apply UPDATE-12 (runs the migrations, adds the 20 samples)
  let { c, p } = await signIn(b, 'system@insania.test'); await p.goto(B + '/app/updates.html'); await p.waitForSelector('#autoBtn'); await p.setInputFiles('#pz', SP + '/u12.zip'); await p.setInputFiles('#ps', SP + '/u12.sig'); await p.click('#autoBtn');
  await p.waitForFunction(() => /Updated to|refused|failed|error/i.test(document.getElementById('found').innerText + document.getElementById('formErr').innerText), null, { timeout: 90000 });
  console.log('   ' + ((await p.locator('#found').innerText()) + (await p.locator('#formErr').innerText())).slice(0, 120).replace(/\n+/g, ' | '));
  ok('UPDATE-12 applied, server on ' + fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim(), fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim() === fs.readFileSync(SP + '/bid24', 'utf8').trim()); await c.close();
  // 2. the short link and the public form (no sign-in)
  const c2 = await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 2 }); c2.setDefaultTimeout(20000); const q = await c2.newPage(); const qerr = []; q.on('pageerror', e => qerr.push(e.message));
  await q.goto(B + '/apply'); await q.waitForSelector('#aform'); ok('/apply opens the public form (' + q.url().replace(B, '') + ')', /app\/apply\.html/.test(q.url()));
  const sections = await q.locator('.asec h2').allInnerTexts(); console.log('   sections:', sections.join(' | ')); const nfields = await q.locator('.afield').count();
  ok('form asks the information (' + nfields + ' questions in ' + sections.length + ' sections)', nfields === 37 && sections.length >= 6);
  for (const k of ['sponsor_name', 'sponsor_file_no', 'status', 'performance', 'organization_comment', 'delay_reason', 'channel_label']) ok('  staff-only "' + k + '" is not on the public form', (await q.locator('#a_' + k).count()) === 0);
  for (const k of ['name_ar', 'nationality', 'health_details', 'madrasa_grade', 'quran_memorization', 'birth_country', 'address_en', 'mother_name_ar', 'mother_works', 'mother_salary', 'aided_siblings', 'other_aid', 'guardian_relation', 'responsible_name_en', 'responsible_since', 'bank_holder', 'bank_last4']) if ((await q.locator('#a_' + k).count()) === 0) ok('  form asks ' + k, false);
  await q.screenshot({ path: SP + '/ap1.png' });
  await q.click('#asubmit'); await q.waitForTimeout(500); ok('empty submit is stopped, required fields flagged', /check/i.test(await q.locator('#a_formErr').innerText()) && /required|name/i.test(await q.locator('#a_name_enErr').innerText()));
  await q.click('#alang'); await q.waitForTimeout(300); ok('Arabic switch: rtl + Arabic title', (await q.evaluate(() => document.dir)) === 'rtl' && /طلب كفالة/.test(await q.locator('h1').first().innerText())); await q.click('#alang');
  const fill = async (k, v) => { const el = q.locator('#a_' + k); if ((await el.evaluate(n => n.tagName)) === 'SELECT') await el.selectOption(v); else await el.fill(v); };
  const data = { name_en: 'Abdulrahman Applicant', name_ar: 'عبد الرحمن', gender: 'm', birth_date: '2017-04-12', nationality: 'الفلبين - Philippines', health_status: 'good', health_details: 'بصحة جيدة', study_level: 'primary', grade: 'الثالث', madrasa_grade: 'الصف الثالث', quran_memorization: 'جزء عم', birth_country: 'الفلبين', residence_country: 'الفلبين', city: 'Manila', address_ar: 'عنوان تجريبي', address_en: 'Sample address', mother_name_ar: 'أم تجريبية', mother_name: 'Sample Mother', mother_marital_status: 'Widow', mother_works: 'no', mother_salary: '0', father_death_date: '2020-01-01', brothers: '2', sisters: '1', aided_siblings: '0', other_aid: '-', guardian_name: 'Sample Guardian Z', guardian_relation: 'عم', guardian_phone: '09171234567', responsible_name_ar: 'مسؤول تجريبي', responsible_name_en: 'Sample Responsible', responsible_relation: 'العم', responsible_since: '2025-03-01', bank_holder: 'Sample Guardian Z', bank_name: 'AUB', bank_last4: '2381' };
  for (const k in data) await fill(k, data[k]);
  await q.setInputFiles('#phin', SP + '/kid1.png'); await q.waitForTimeout(300); ok('photo added and previewed', (await q.locator('.aph').count()) === 1);
  await q.click('#asubmit'); await q.waitForSelector('.aref', { timeout: 20000 }); const ref = await q.locator('.aref').innerText(); ok('submitted, reference shown: ' + ref, /^APP-\d{5}$/.test(ref)); await q.screenshot({ path: SP + '/ap2.png' });
  ok('no script errors on the public form', qerr.length === 0); await c2.close();
  // 3. Aytam staff review
  let errs; ({ c, p, errs } = await signIn(b, 'aytam@insania.test'));
  for (let i = 0; i < 2; i++) { await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(2500); }
  ok('Aytam menu: ' + await tab(p), /Applications/.test(await tab(p)) && /Orphans/.test(await tab(p)));
  ok('Home shows "New applications" = 1', (await p.locator('.fb-stat-link .fb-num').first().innerText()).trim() === '1' && /New applications/.test(await p.locator('.fb-stat-link').first().innerText()));
  await p.goto(B + '/app/people.html'); await p.waitForTimeout(3000); const lst = await p.locator('#view').innerText();
  ok('Orphans: 20 sample orphans (AY-001…) with Sample tag + Remove button', /AY-001/.test(lst) && (await p.locator('.badge:has-text("Sample")').count()) >= 15 && /Remove sample orphans \(20\)/.test(lst));
  await p.goto(B + '/app/applications.html'); await p.waitForSelector('#applyLink'); ok('Applications: link ' + await p.inputValue('#applyLink'), /\/apply$/.test(await p.inputValue('#applyLink')));
  await p.screenshot({ path: SP + '/ap3.png' }); ok('the new application is listed', /Abdulrahman Applicant/.test(await p.locator('#aplist').innerText()));
  await p.click('#aplist a.item'); await p.waitForURL(/application\.html/); await p.waitForTimeout(3000); const det = await p.locator('#view').innerText();
  ok('review page shows the answers, no staff-only fields', /Sample Guardian Z/.test(det) && /09171234567/.test(det) && /2381/.test(det) && /الفلبين/.test(det) && !/Sponsor file no|Organization comment/.test(det));
  ok('photo shown', await p.waitForFunction(() => { const b = document.querySelector('.ph'); return b && b.style.backgroundImage.indexOf('blob:') > -1; }, null, { timeout: 8000 }).then(() => true, () => false)); await p.screenshot({ path: SP + '/ap4.png' });
  p.once('dialog', d => d.accept()); await p.click('[data-act=approve]'); await p.waitForURL(/person\.html\?id=/, { timeout: 20000 }); await p.waitForTimeout(3000); const prof = await p.locator('#view').innerText();
  ok('approved: profile opens with all the information', /Abdulrahman Applicant/.test(prof) && /عبد الرحمن/.test(prof) && /Sample Mother/.test(prof) && /Sample Guardian Z/.test(prof) && /Philippines/.test(prof) && /Form submitted/.test(prof) && /Photos uploaded/.test(prof));
  await p.screenshot({ path: SP + '/ap5.png', fullPage: true });
  ok('profile photo loads', await p.waitForFunction(() => { const b = document.querySelector('.ph'); return b && b.style.backgroundImage.indexOf('blob:') > -1; }, null, { timeout: 8000 }).then(() => true, () => false));
  // 4. edit form carries every field; save a staff-only field
  await p.click('[data-act=editprofile]'); await p.waitForSelector('#editForm'); const nEdit = await p.locator('#editForm [id^=f_]').count(); ok('edit form has every information field (' + nEdit + ')', nEdit >= 48);
  ok('edit form prefilled (name, guardian phone, madrasa grade)', (await p.inputValue('#f_name_en')) === 'Abdulrahman Applicant' && (await p.inputValue('#f_guardian_phone')) === '09171234567' && (await p.inputValue('#f_madrasa_grade')) === 'الصف الثالث');
  await p.evaluate(() => document.querySelectorAll('#editForm details').forEach(d => d.open = true)); await p.selectOption('#f_performance', 'average'); await p.fill('#f_sponsor_name', 'Sample Sponsor A'); await p.fill('#f_sponsor_file_no', 'SP-1001'); await p.fill('#f_organization_comment', 'شعر اليتيم بوجود من يسانده');
  await p.click('#editForm .btn-main'); await p.waitForTimeout(3000); const after = await p.locator('#view').innerText(); ok('staff-only fields saved and shown (Sponsor, rating, comment)', /Sample Sponsor A/.test(after) && /SP-1001/.test(after) && /Average/.test(after) && /شعر اليتيم/.test(after));
  // 5. sample orphans: open one, then remove only samples
  await p.goto(B + '/app/people.html'); await p.waitForTimeout(2500); ok('real orphan + 12 older demo orphans stay: remove button counts only samples', /Remove sample orphans \(20\)/.test(await p.locator('#view').innerText()));
  p.once('dialog', d => d.accept()); await p.click('[data-act=rmsamples]'); await p.waitForTimeout(3500); const after2 = await p.locator('#view').innerText();
  ok('samples removed, Abdulrahman Applicant kept', !/AY-0/.test(after2) && /Abdulrahman Applicant/.test(after2) && !/Remove sample/.test(after2));
  ok('no script errors (Aytam)', errs.length === 0); await c.close();
  // 6. everyone else: no applications
  for (const [who, email] of [['President', 'president@insania.test'], ['Finance', 'finance@insania.test'], ['System Admin', 'system@insania.test']]) { ({ c, p } = await signIn(b, email)); await p.goto(B + '/app/applications.html'); await p.waitForTimeout(2000); ok(who + ': Applications closed', !/Application form link|Abdulrahman/.test(await p.locator('body').innerText())); await c.close(); }
  await b.close(); console.log(bad ? 'FAILURES ' + bad : 'APPLY CHECKS PASSED'); })().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 6).join(' | ')); process.exit(1); });
