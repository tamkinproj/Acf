const { chromium } = require('playwright'); const B = 'http://127.0.0.1:8090';
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
async function login(b, email, pw) { const c = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', email); await p.fill('#lPass', pw); await Promise.all([p.waitForNavigation({ url: /(dashboard|setup)\.html/ }), p.click('button[type=submit]')]); p.errs = errs; return p; }
(async () => {
  const b = await chromium.launch(); const PW = 'Str0ng#Passw0rd';
  // 1. System Admin (created by the installer): first login -> checklist: only the President Admin is required
  let p = await login(b, 'admin@alinsania.test', PW); await p.waitForSelector('#view .item'); await p.waitForTimeout(500);
  ok(/setup\.html/.test(p.url()), 'System Admin first login lands on the setup checklist');
  const steps = await p.locator('.item b').allInnerTexts(); ok(/President Admin/.test(steps[0]) && !steps.some(x => /Security Admin|first child/i.test(x)), 'first step is the President Admin; nothing about other roles or children: ' + steps.join(' | '));
  ok(await p.locator('[data-act=stepsheet]').count() === 1, 'the only account the System Admin can create is the President');
  await p.click('[data-act=stepsheet][data-role=president_admin]'); await p.fill('#sa_name', 'Paul President'); await p.fill('#sa_email', 'paul@alinsania.test'); await p.fill('#sa_password', PW); await p.click('#saForm button[type=submit]');
  await p.waitForFunction(() => /✓/.test((document.querySelector('.item .badge') || { innerText: '' }).innerText), null, { timeout: 8000 }); ok(true, 'President Admin created by the System Admin');
  await p.goto(B + '/app/dashboard.html'); await p.waitForSelector('.stat'); ok(!/Accounts|Activity|People|Payments/.test(await p.locator('.menu').innerText()), 'System Admin menu has no accounts, people or money: ' + (await p.locator('.menu').innerText()).replace(/\n/g, ' | '));
  const r = await fetch(B + '/server/api/login', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: 'admin@alinsania.test', password: PW }) }).then(x => x.json());
  const code = async u => (await fetch(B + '/server/api/' + u, { headers: { Authorization: 'Bearer ' + r.data.token, Accept: 'application/json' } })).status;
  const bad = []; for (const u of ['users', 'audit', 'children', 'payments', 'projects', 'reports/summary', 'site']) if (await code(u) !== 403) bad.push(u); ok(bad.length === 0, 'server refuses the System Admin on everything the President owns (users, audit, children, payments, projects, reports, website)' + (bad.length ? ' OPEN: ' + bad : ''));
  ok(p.errs.length === 0, 'no JS errors (system admin)');
  // 2. President: first login -> checklist starts with the first child; creates the rest of the roles
  p = await login(b, 'paul@alinsania.test', PW); await p.waitForTimeout(800); ok(/setup\.html/.test(p.url()), 'President Admin first login lands on the checklist');
  ok(/Add the first child/.test(await p.locator('.item').first().innerText()), 'first step: add the first child');
  const names = await p.locator('.item b').allInnerTexts(); ok(['Aytam', 'Finance', 'Projects', 'Security Admin', 'Admin Maintenance'].every(w => names.some(n => n.includes(w))), 'the checklist offers every other role to create: ' + names.join(' | '));
  await p.goto(B + '/app/users.html?add=1&role=security_admin'); await p.waitForSelector('#userForm'); ok((await p.inputValue('#u_role')) === 'security_admin', 'Security Admin link opens the form with the role chosen');
  await p.fill('#u_name', 'Sara Security'); await p.fill('#u_email', 'sara@alinsania.test'); await p.fill('#u_password', PW); await p.click('#userForm button[type=submit]'); await p.waitForSelector('.list .item >> text=Sara Security'); ok(true, 'President created a Security Admin');
  await p.goto(B + '/app/users.html?add=1&role=staff&dept=finance'); await p.waitForSelector('#userForm'); ok((await p.inputValue('#u_department_key')) === 'finance' && (await p.inputValue('#u_role')) === 'staff', 'staff link preselects role and department');
  await p.fill('#u_name', 'Fin Staff'); await p.fill('#u_email', 'fin@alinsania.test'); await p.fill('#u_password', PW); await p.click('#userForm button[type=submit]'); await p.waitForSelector('.list .item >> text=Fin Staff'); ok(true, 'President created Finance staff');
  const roleOpts = await (async () => { await p.click('.fab'); await p.waitForSelector('#userForm'); return p.locator('#u_role option').allInnerTexts(); })(); ok(!roleOpts.some(o => /System Admin/.test(o)), 'System Admin cannot be created from Accounts');
  await p.click('#sheetRoot [data-act=close]');
  await p.goto(B + '/app/people.html?add=1'); await p.waitForSelector('#addForm'); await p.fill('#f_name_en', 'First Child'); await p.selectOption('#f_pay_type', 'cash'); await p.click('#addForm button[type=submit]'); await p.waitForSelector('#plist .item >> text=First Child');
  await p.goto(B + '/app/setup.html'); await p.waitForSelector('.card'); ok(/Everything required is done/.test(await p.locator('#view').innerText()), 'checklist shows everything required is done');
  ok(p.errs.length === 0, 'no JS errors (president)');
  // 3. Security Admin created by the President can sign in and manage accounts
  p = await login(b, 'sara@alinsania.test', PW); await p.waitForTimeout(600); await p.goto(B + '/app/users.html'); await p.waitForSelector('.list .item'); ok(await p.locator('.list .item').count() >= 4, 'the Security Admin made by the President can manage accounts');
  // 4. Finance staff made by the President sees only payments and reports
  p = await login(b, 'fin@alinsania.test', PW); await p.waitForTimeout(500); ok(/Payments/.test(await p.locator('.menu').innerText()) && !/People|Accounts|Website/.test(await p.locator('.menu').innerText()), 'Finance staff sees only their own screens: ' + (await p.locator('.menu').innerText()).replace(/\n/g, ' | '));
  await b.close(); console.log(process.exitCode ? 'WIZARD CHECKS FAILED' : 'WIZARD CHECKS PASSED');
})().catch(e => { console.error('FAILED', e.message.split('\n').slice(0, 5).join(' | ')); process.exit(1); });
