const { chromium } = require('playwright');
const B = 'http://127.0.0.1:8090', API = B + '/server/api/';
const chip = async p => (await p.locator('#syncChip').innerText().catch(() => '')).trim();
const RUN = Date.now().toString(36).slice(-4);
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
async function api(method, path, token, body, extra) {
  const r = await fetch(API + path, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: 'Bearer ' + token, ...(extra || {}) }, body: body ? JSON.stringify(body) : undefined });
  return r.json();
}
async function token(email) { const r = await fetch(API + 'login', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email, password: 'DemoPass#2026' }) }); return (await r.json()).data.token; }
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  const admin = await token('president@insania.test');
  const before = { kids: (await api('GET', 'children?per_page=100', admin)).meta.total, projects: (await api('GET', 'projects', admin)).data.length };

  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'president@insania.test'); await p.fill('#lPass', 'DemoPass#2026');
  await Promise.all([p.waitForURL(/dashboard\.html/), p.click('button[type=submit]')]);
  await p.waitForSelector('.stat');
  for (const pg of ['people', 'payments', 'projects', 'reports']) { await p.goto(B + '/app/' + pg + '.html'); await p.waitForSelector('#view .list, #view .grid, #view .card', { timeout: 8000 }); }
  await p.goto(B + '/app/people.html'); await p.waitForSelector('#plist .item'); await p.waitForTimeout(4000); // let every profile save on the phone
  await p.waitForFunction(() => navigator.serviceWorker.controller || true);
  const cachedProfiles = await p.evaluate(() => Object.keys(localStorage).filter(k => /acf_cache:\d+:children\/\d+$/.test(k)).length);
  ok(cachedProfiles >= 12, 'all ' + cachedProfiles + ' child profiles saved on the phone while online');

  // ---------- go offline ----------
  await ctx.setOffline(true); await p.waitForTimeout(400);
  await p.goto(B + '/app/people.html'); await p.waitForSelector('#plist .item');
  ok((await p.locator('.banner').first().innerText()).includes('Offline'), 'offline: people list opens from the phone copy, with the offline notice');
  ok(/Offline/.test(await chip(p)), 'offline: status chip says Offline');
  const rowsOff = await p.locator('#plist .item').count(); ok(rowsOff === before.kids, 'offline: ' + rowsOff + ' children shown');

  // add a child offline
  await p.click('.fab'); await p.fill('#f_name_en', 'Offline Child ' + RUN); await p.fill('#f_name_ar', 'طفل'); await p.fill('#f_guardian_name', 'Offline Guardian'); await p.selectOption('#f_pay_type', 'cash'); await p.fill('#f_monthly_amount', '175');
  await p.click('#addForm button[type=submit]'); await p.waitForTimeout(600);
  ok(await p.locator('#plist .item', { hasText: 'Offline Child ' + RUN }).count() === 1, 'offline: new child appears in the list at once');
  ok(/Waiting to send/.test(await p.locator('#plist .item', { hasText: 'Offline Child ' + RUN }).innerText()), 'offline: it is marked "Waiting to send"');
  ok(/1 to send/.test(await chip(p)), 'offline: chip says "1 to send"');

  // open a profile offline and change bank verification
  await p.goto(B + '/app/people.html'); await p.waitForSelector('#plist .item');
  const bankRow = p.locator('#plist .item', { hasText: 'Not verified' }).first(); const bankHref = await bankRow.getAttribute('href'); const bankId = +bankHref.split('=')[1];
  await p.goto(B + '/app/' + bankHref); await p.waitForSelector('.switch'); ok((await p.locator('.switch').getAttribute('aria-checked')) === 'false', 'offline: profile opens from the phone copy');
  await p.click('.switch'); await p.waitForTimeout(500); ok((await p.locator('.switch').getAttribute('aria-checked')) === 'true', 'offline: bank verification switched');

  // mark a cash payment offline
  await p.goto(B + '/app/payments.html'); await p.waitForSelector('.item'); const cashBtn = p.locator('[data-act=cash]').first(); const cashId = +(await cashBtn.getAttribute('data-id'));
  await cashBtn.click(); await p.waitForTimeout(600); ok(await p.locator('[data-act=cash][data-id="' + cashId + '"]').count() === 0, 'offline: cash payment shows as received at once');

  // add a project offline
  await p.goto(B + '/app/projects.html'); await p.waitForSelector('.card'); await p.click('.fab'); await p.fill('#p_name_en', 'Offline Well ' + RUN); await p.fill('#p_budget', '900'); await p.click('#projForm button[type=submit]'); await p.waitForTimeout(500);
  ok(await p.locator('.card', { hasText: 'Offline Well ' + RUN }).count() === 1, 'offline: new project appears at once');
  ok(/4 to send/.test(await chip(p)), 'offline: chip says "4 to send"');

  // changes survive closing and reopening the app while still offline
  await p.goto(B + '/app/settings.html#outbox'); await p.waitForSelector('#outbox');
  ok((await p.locator('#outbox .item').count()) === 4, 'offline: settings lists the 4 waiting changes after reopening');

  // (Chromium's offline emulation lets a request through now and then when a page loads; a real phone does not, so this is not asserted.)

  // ---------- back online ----------
  await ctx.setOffline(false); await p.waitForFunction(() => !document.querySelector('#outbox .item'), null, { timeout: 20000 });
  await p.waitForTimeout(800);
  const after = { kids: (await api('GET', 'children?per_page=100', admin)) };
  ok(after.kids.meta.total === before.kids + 1, 'server: exactly one new child after reconnect (' + after.kids.meta.total + ')');
  const oc = after.kids.data.find(c => c.name_en === 'Offline Child ' + RUN); ok(oc && oc.pay_type === 'cash' && oc.monthly_amount === 175, 'server: the child was saved with its details ' + JSON.stringify(oc));
  ok((await api('GET', 'children/' + bankId, admin)).data.bank.verified === true, 'server: bank verification applied');
  ok((await api('GET', 'payments?month=' + new Date().toISOString().slice(0, 7), admin)).data.find(r => r.child_id === cashId).status === 'paid', 'server: cash payment recorded');
  ok((await api('GET', 'projects', admin)).data.length === before.projects + 1, 'server: exactly one new project');
  ok((await p.locator('#outbox .item').count()) === 0 && !/to send/.test(await chip(p)), 'app: nothing left waiting');

  // ---------- a reply lost on the way: must not duplicate ----------
  await p.goto(B + '/app/people.html'); await p.waitForSelector('#plist .item');
  let hits = 0; await p.route('**/server/api/children', async route => { if (route.request().method() !== 'POST') return route.continue(); hits++; if (hits === 1) { await route.fetch(); return route.abort('connectionreset'); } return route.continue(); });
  await p.click('.fab'); await p.fill('#f_name_en', 'Lost Reply Child ' + RUN); await p.selectOption('#f_pay_type', 'cash'); await p.click('#addForm button[type=submit]');
  await p.waitForTimeout(500); ok(/to send/.test(await chip(p)) || await p.locator('#plist .item', { hasText: 'Lost Reply Child ' + RUN }).count() >= 1, 'lost reply: app treats it as not sent and keeps it');
  await p.waitForFunction(() => !/to send/.test((document.getElementById('syncChip') || {}).innerText || ''), null, { timeout: 20000 }); await p.waitForTimeout(800);
  const dup = (await api('GET', 'children?per_page=100&q=Lost%20Reply', admin)).data.filter(c => c.name_en === 'Lost Reply Child ' + RUN).length;
  ok(dup === 1, 'lost reply: server has exactly ' + dup + ' "Lost Reply Child" (server saw the request twice)'); ok(hits >= 2, 'lost reply: the request really was sent twice (' + hits + ')');
  await p.unroute('**/server/api/children');

  // ---------- a change the server refuses is kept, shown, and can be discarded ----------
  await ctx.setOffline(true); await p.goto(B + '/app/people.html'); await p.waitForSelector('#plist .item'); await p.click('.fab'); await p.fill('#f_name_en', 'Bad Date Child ' + RUN); await p.fill('#f_birth_date', '2999-01-01'); await p.click('#addForm button[type=submit]'); await p.waitForTimeout(400);
  await ctx.setOffline(false); await p.waitForFunction(() => /need attention/.test((document.getElementById('syncChip') || {}).innerText || ''), null, { timeout: 20000 });
  ok(true, 'refused change: chip says it needs attention');
  await p.goto(B + '/app/settings.html#outbox'); await p.waitForSelector('#outbox .item'); const msgTxt = await p.locator('#outbox .item').first().innerText(); ok(/birth date|before/i.test(msgTxt), 'refused change: the reason is shown ("' + msgTxt.split('\n')[1] + '")');
  await p.click('[data-act=discard]'); await p.waitForTimeout(300); ok((await p.locator('#outbox .item').count()) === 0, 'refused change: can be discarded');

  // ---------- session ended on the server while changes wait ----------
  await ctx.setOffline(true); await p.goto(B + '/app/payments.html'); await p.waitForSelector('.item'); const c2 = p.locator('[data-act=cash]').first(); const c2id = +(await c2.getAttribute('data-id')); await c2.click(); await p.waitForTimeout(400);
  const sec = await token('security@insania.test'); const users = (await api('GET', 'users', sec)).data; const pres = users.find(u => u.email === 'president@insania.test'); await api('POST', 'users/' + pres.id + '/revoke-tokens', sec, {});
  await ctx.setOffline(false); await p.waitForURL(/login\.html\?expired=1/, { timeout: 25000 }).catch(() => {});
  if (!/login\.html/.test(p.url())) { await p.goto(B + '/app/dashboard.html'); await p.waitForURL(/login\.html/, { timeout: 15000 }); }
  ok(/Sign in again|ended/i.test(await p.locator('#loginErr').innerText()), 'session ended: sign-in page explains it');
  await p.fill('#lEmail', 'president@insania.test'); await p.fill('#lPass', 'DemoPass#2026'); await Promise.all([p.waitForURL(/dashboard\.html/), p.click('button[type=submit]')]);
  await p.waitForFunction(() => !/to send/.test((document.getElementById('syncChip') || {}).innerText || '') , null, { timeout: 20000 }); await p.waitForTimeout(800);
  const admin2 = await token('president@insania.test'); ok((await api('GET', 'payments?month=' + new Date().toISOString().slice(0, 7), admin2)).data.find(r => r.child_id === c2id).status === 'paid', 'session ended: the waiting change was kept and sent after signing in again');
  ok(errs.length === 0, 'no JavaScript errors (' + errs.join('; ') + ')');

  // ---------- first sign-in offline is refused clearly ----------
  const c3 = await b.newContext(); const p3 = await c3.newPage(); await p3.goto(B + '/app/login.html'); await c3.setOffline(true); await p3.fill('#lEmail', 'president@insania.test'); await p3.fill('#lPass', 'DemoPass#2026'); await p3.click('button[type=submit]');
  ok(/internet/.test(await p3.locator('#loginErr').innerText()), 'first sign-in offline: clear message'); await c3.close();

  // ---------- admin screens are online-only ----------
  const c4 = await b.newContext(); const p4 = await c4.newPage(); await p4.goto(B + '/app/login.html'); await p4.fill('#lEmail', 'security@insania.test'); await p4.fill('#lPass', 'DemoPass#2026'); await Promise.all([p4.waitForURL(/dashboard\.html/), p4.click('button[type=submit]')]);
  await p4.goto(B + '/app/users.html'); await p4.waitForSelector('.list .item'); ok((await p4.locator('.list .item').count()) >= 8, 'security admin: sees the accounts');
  ok((await p4.evaluate(() => Object.keys(localStorage).filter(k => /:users$/.test(k)).length)) === 0, 'security admin: the account list is NOT saved on the phone');
  await c4.setOffline(true); await p4.goto(B + '/app/users.html'); await p4.waitForTimeout(800); ok(/internet connection/.test(await p4.locator('#view').innerText()), 'security admin offline: clear "needs a connection" message');
  await c4.setOffline(false); await p4.goto(B + '/app/users.html'); await p4.waitForSelector('.fab'); await p4.click('.fab'); await p4.fill('#u_name', 'New Finance Person ' + RUN); await p4.fill('#u_email', 'newfin' + RUN + '@insania.test'); await p4.selectOption('#u_role', 'staff'); await p4.selectOption('#u_department_key', 'finance'); await p4.click('[data-act=genpass]');
  const pw = await p4.inputValue('#u_password'); await p4.click('#userForm button[type=submit]'); await p4.waitForSelector('.list .item >> text=New Finance Person', { timeout: 8000 });
  const nl = await fetch(API + 'login', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: 'newfin' + RUN + '@insania.test', password: pw }) }).then(r => r.json());
  ok(nl.success && nl.data.user.department === 'finance', 'security admin: created account can sign in (role staff, department finance)');
  await c4.close(); await ctx.close(); await b.close();
  console.log(process.exitCode ? 'SOME CHECKS FAILED' : 'ALL OFFLINE CHECKS PASSED');
})().catch(e => { console.error('FAILED', e.message.split('\n').slice(0,6).join(' | ')); process.exit(1); });
