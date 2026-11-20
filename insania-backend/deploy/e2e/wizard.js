const { chromium } = require('playwright'); const B = 'http://127.0.0.1:8090';
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
async function login(b, email, pw) { const c = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', email); await p.fill('#lPass', pw); await Promise.all([p.waitForNavigation({ url: /(dashboard|setup)\.html/ }), p.click('button[type=submit]')]); p.errs = errs; return p; }
(async () => {
  const b = await chromium.launch(); const PW = 'Str0ng#Passw0rd';
  // 1. the System Admin's first login goes straight to the checklist
  let p = await login(b, 'admin@alinsania.test', PW); await p.waitForSelector('#view .list, #view .card'); await p.waitForTimeout(600);
  ok(/setup\.html/.test(p.url()), 'System Admin first login lands on the setup checklist');
  ok((await p.locator('.item').first().innerText()).includes('Create the Security Admin'), 'first step: create the Security Admin (required)');
  await p.click('[data-act=stepsheet]'); await p.fill('#sa_name', 'Sara Security'); await p.fill('#sa_email', 'sara@alinsania.test'); await p.fill('#sa_password', PW); await p.click('#saForm button[type=submit]');
  await p.waitForFunction(() => document.querySelector('.item .badge') && /✓/.test(document.querySelector('.item .badge').innerText), null, { timeout: 8000 }); ok(true, 'Security Admin created from the checklist; step turns done');
  // second visit does not redirect again
  await p.goto(B + '/app/dashboard.html'); await p.waitForSelector('.stat'); await p.waitForTimeout(500); ok(/dashboard\.html/.test(p.url()), 'seen once: the dashboard no longer redirects');
  ok(await p.locator('text=Getting started').count() >= 1, 'dashboard keeps a "Getting started" card while steps remain');
  ok(p.errs.length === 0, 'no JS errors (system admin)');
  // 2. Security Admin: first login -> checklist -> President via preselected form
  p = await login(b, 'sara@alinsania.test', PW); await p.waitForTimeout(800); ok(/setup\.html/.test(p.url()), 'Security Admin first login lands on the checklist');
  const firstStep = await p.locator('.item').first().innerText(); ok(/President Admin/.test(firstStep), 'first step: create the President Admin');
  await p.click('.item >> nth=0 >> a'); await p.waitForURL(/users\.html\?add=1&role=president_admin/); await p.waitForSelector('#userForm');
  ok((await p.inputValue('#u_role')) === 'president_admin', 'the account form opens with President Admin already chosen');
  await p.fill('#u_name', 'Paul President'); await p.fill('#u_email', 'paul@alinsania.test'); await p.fill('#u_password', PW); await p.click('#userForm button[type=submit]'); await p.waitForSelector('.list .item >> text=Paul President');
  // staff step preselects the department
  await p.goto(B + '/app/users.html?add=1&role=staff&dept=finance'); await p.waitForSelector('#userForm'); ok((await p.inputValue('#u_department_key')) === 'finance' && (await p.inputValue('#u_role')) === 'staff', 'staff link preselects role and department');
  ok(p.errs.length === 0, 'no JS errors (security admin)');
  // 3. President: first login -> checklist -> add first child
  p = await login(b, 'paul@alinsania.test', PW); await p.waitForTimeout(800); ok(/setup\.html/.test(p.url()), 'President Admin first login lands on the checklist');
  ok(/Add the first child/.test(await p.locator('.item').first().innerText()), 'first step: add the first child');
  await p.click('.item >> nth=0 >> a'); await p.waitForURL(/people\.html\?add=1/); await p.waitForSelector('#addForm'); ok(true, 'the add-child form opens by itself');
  await p.fill('#f_name_en', 'First Child'); await p.selectOption('#f_pay_type', 'cash'); await p.click('#addForm button[type=submit]'); await p.waitForSelector('#plist .item >> text=First Child');
  await p.goto(B + '/app/setup.html'); await p.waitForSelector('.card'); ok(/Everything required is done/.test(await p.locator('#view').innerText()), 'checklist shows everything required is done');
  ok(p.errs.length === 0, 'no JS errors (president)');
  await b.close(); console.log(process.exitCode ? 'WIZARD CHECKS FAILED' : 'WIZARD CHECKS PASSED');
})().catch(e => { console.error('FAILED', e.message.split('\n').slice(0, 5).join(' | ')); process.exit(1); });
