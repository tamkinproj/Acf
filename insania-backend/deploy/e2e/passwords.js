const { chromium } = require('playwright');
const B = 'http://127.0.0.1:8090'; let bad = 0;
const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
async function login(b, email, pw) {
  const c = await b.newContext(); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', email); await p.fill('#lPass', pw); await p.click('button[type=submit]');
  await p.waitForTimeout(1500); return { c, p, errs };
}
(async () => {
  const b = await chromium.launch();
  // 1. security admin changes own password in Settings
  let { c, p, errs } = await login(b, 'security@insania.test', 'DemoPass#2026'); ok('security signs in', /dashboard/.test(p.url()));
  await p.goto(B + '/app/settings.html'); await p.click('[data-act=changepw]'); await p.waitForSelector('#pwForm');
  await p.fill('#pw_current_password', 'wrong'); await p.fill('#pw_password', 'Brand#NewPass2026'); await p.fill('#pw_again', 'Different#Pass2026'); await p.click('#pwForm button[type=submit]');
  ok('mismatch caught', /not the same/.test(await p.locator('#pw_againErr').innerText()));
  await p.fill('#pw_again', 'Brand#NewPass2026'); await p.click('#pwForm button[type=submit]'); await p.waitForTimeout(1000);
  ok('wrong current password shown', /not right/.test(await p.locator('#pw_current_passwordErr').innerText()));
  await p.fill('#pw_current_password', 'DemoPass#2026'); await p.click('#pwForm button[type=submit]'); await p.waitForTimeout(1500);
  ok('password changed, sheet closed', (await p.locator('#pwForm').count()) === 0); await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(800); ok('still signed in', /dashboard/.test(p.url())); await c.close();
  ({ c, p } = await login(b, 'security@insania.test', 'DemoPass#2026')); ok('old password refused', /login/.test(p.url())); await c.close();
  ({ c, p } = await login(b, 'security@insania.test', 'Brand#NewPass2026')); ok('new password works', /dashboard/.test(p.url())); await c.close();
  // 2. system admin changes president
  ({ c, p, errs } = await login(b, 'system@insania.test', 'DemoPass#2026')); ok('system signs in', /dashboard/.test(p.url()));
  await p.goto(B + '/app/system.html'); await p.waitForSelector('[data-act=chgpres]'); ok('president card shows', /president@insania.test/.test(await p.locator('#view').innerText()));
  ok('no office data on that page', !/children|payments/i.test((await p.locator('#view').innerText()).replace(/President Admin/g, '')));
  await p.click('[data-act=chgpres]'); await p.waitForSelector('#presForm');
  await p.fill('#pr_name', 'Amina New President'); await p.fill('#pr_email', 'amina@insania.test'); await p.click('[data-act=prgen]'); const gen = await p.inputValue('#pr_password');
  await p.fill('#pr_admin_password', 'wrongwrong'); await p.click('#presForm button[type=submit]'); await p.waitForTimeout(1000);
  ok('wrong confirm password refused', /not right/.test(await p.locator('#pr_admin_passwordErr').innerText()));
  await p.fill('#pr_admin_password', 'DemoPass#2026'); await p.click('#presForm button[type=submit]'); await p.waitForTimeout(1800);
  ok('card updated', /amina@insania.test/.test(await p.locator('#view').innerText())); ok('generated password length', gen.length === 16); await c.close();
  ({ c, p } = await login(b, 'president@insania.test', 'DemoPass#2026')); ok('old president refused', /login/.test(p.url())); await c.close();
  ({ c, p } = await login(b, 'amina@insania.test', gen)); ok('new president signs in and sees the office', /dashboard/.test(p.url()) && /Website|Accounts/.test(await p.locator('.menu').innerText())); await c.close();
  // 3. president cannot see the card / endpoint
  ({ c, p } = await login(b, 'amina@insania.test', gen)); await p.goto(B + '/app/system.html'); await p.waitForTimeout(1000); ok('president cannot open System page', !/Change President Admin/.test(await p.locator('body').innerText())); await c.close();
  await b.close(); console.log(bad ? 'FAILURES ' + bad : 'PASSWORD CHECKS PASSED');
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
