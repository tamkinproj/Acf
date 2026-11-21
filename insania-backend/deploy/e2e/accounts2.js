const { chromium, devices } = require('playwright'); const B = 'http://127.0.0.1:8090';
let bad = 0; const ok = (n, c) => { console.log((c ? 'PASS ' : 'FAIL ') + n); if (!c) bad++; };
const tab = async p => (await p.locator('#tabbar').innerText({ timeout: 3000 }).catch(() => '')).replace(/\s+/g, ' ').trim();
async function signIn(b, email, pw) { const c = await b.newContext({ ...devices['iPhone 13'] }); c.setDefaultTimeout(20000); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', email); await p.fill('#lPass', pw); await p.click('button[type=submit]'); await p.waitForTimeout(3500); return { c, p, errs }; }
(async () => {
  const b = await chromium.launch(); let { c, p, errs } = await signIn(b, 'president@insania.test', 'DemoPass#2026');
  for (let i = 0; i < 2; i++) { await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(3000); }
  const home = await p.locator('#view').innerText();
  ok('President menu: ' + await tab(p), /Accounts|Users/.test(await tab(p)) && /Settings/.test(await tab(p)) && !/People|Payments|Projects|Website/.test(await tab(p)));
  ok('Home shows Getting started (' + (home.match(/\d+ of \d+ steps done/) || ['?'])[0] + ') and "more features" note', /Getting started/.test(home) && /switched on one by one/.test(home));
  await p.goto(B + '/app/setup.html'); await p.waitForTimeout(2500); const steps = await p.locator('#view').innerText();
  ok('Getting started lists staff, Security Admin and Maintenance steps, nothing else', /Aytam staff/.test(steps) && /Security Admin/.test(steps) && /Maintenance/.test(steps) && !/first child|Publish your website|Add a project/i.test(steps));
  // step "Create Aytam staff" opens the account form, already set to Staff / Aytam
  await p.goto(B + '/app/users.html?add=1&role=staff&dept=aytam'); await p.waitForSelector('#u_role');
  ok('role choices: ' + (await p.locator('#u_role option').allInnerTexts()).join(', '), !(await p.locator('#u_role option').allInnerTexts()).some(x => /President|System/.test(x)));
  ok('form preset to Staff / Aytam', (await p.inputValue('#u_role')) === 'staff' && (await p.inputValue('#u_department_key')) === 'aytam');
  await p.fill('#u_name', 'Aisha Aytam'); await p.fill('#u_email', 'aisha@insania.test'); await p.fill('#u_password', 'Aytam#Passw0rd1'); await p.click('#sheetRoot button[type=submit]'); await p.waitForTimeout(2500);
  await p.goto(B + '/app/users.html?add=1&role=security_admin'); await p.waitForSelector('#u_role');
  await p.fill('#u_name', 'Samir Security'); await p.fill('#u_email', 'samir@insania.test'); await p.fill('#u_password', 'Secur#Passw0rd1'); await p.click('#sheetRoot button[type=submit]'); await p.waitForTimeout(2500);
  await p.goto(B + '/app/users.html'); await p.waitForTimeout(2500); const list = await p.locator('#view').innerText();
  ok('both new accounts listed', /Aisha Aytam/.test(list) && /Samir Security/.test(list));
  await p.locator('.item', { hasText: 'President Admin' }).first().click().catch(() => {}); await p.waitForTimeout(800);
  ok('President row is locked: "' + (await p.locator('#toast').innerText().catch(() => '')) + '"', /managed by the System Admin/.test(await p.locator('#toast').innerText().catch(() => '')));
  ok('no script errors (President)', errs.length === 0); await c.close();
  for (const [email, pw] of [['aisha@insania.test', 'Aytam#Passw0rd1'], ['samir@insania.test', 'Secur#Passw0rd1']]) {
    ({ c, p, errs } = await signIn(b, email, pw)); await p.goto(B + '/app/dashboard.html'); await p.waitForTimeout(3000);
    ok(email.split('@')[0] + ' signs in, menu: ' + await tab(p), (await tab(p)) === 'Home Settings' && errs.length === 0); await c.close();
  }
  await b.close(); console.log(bad ? 'FAILURES ' + bad : 'ACCOUNTS CHECKS PASSED');
})().catch(e => { console.error('ERR', e.message.split('\n').slice(0, 4).join(' | ')); process.exit(1); });
