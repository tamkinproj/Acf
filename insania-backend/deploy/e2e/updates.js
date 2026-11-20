const { chromium } = require('playwright'); const { execSync } = require('child_process'); const fs = require('fs');
const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site';
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const build = () => fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim();
const table = () => execSync(`mariadb -uroot -S ${SP}/mysql/run/m.sock insania -N -e "show tables like 'patch_probe'"`).toString().trim() === 'patch_probe';
const status = async path => (await fetch(B + path, { redirect: 'manual' })).status;
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, acceptDownloads: true }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'admin@alinsania.test'); await p.fill('#lPass', 'Str0ng#Passw0rd'); await Promise.all([p.waitForNavigation({ url: /(dashboard|setup)\.html/ }), p.click('button[type=submit]')]);
  const startBuild = build(); const bdir = SITE + '/insania_app/storage/app/updates/backups'; const backupsBefore = fs.existsSync(bdir) ? fs.readdirSync(bdir).length : 0;
  await p.goto(B + '/app/updates.html'); await p.waitForSelector('#patchForm');
  ok(/Updates/.test(await p.locator('.menu').innerText()), 'Updates is in the System Admin menu');
  ok((await p.locator('.card').first().innerText()).includes(startBuild), 'page shows the build on the server (' + startBuild + ')');
  const check = async (zip, sig) => { await p.goto(B + '/app/updates.html'); await p.waitForSelector('#patchForm'); await p.setInputFiles('#pz', zip); await p.setInputFiles('#ps', sig); await p.click('#checkBtn'); await p.waitForFunction(() => document.getElementById('formErr').textContent || document.getElementById('found').style.display !== 'none' && document.getElementById('found').innerHTML.length > 20, null, { timeout: 15000 }); return (await p.locator('#formErr').innerText()).trim(); };

  // ---- refused patches change nothing
  let e = await check(`${SP}/patch_good.zip`, `${SP}/patch_good_wrongkey.sig`); ok(/signature does not match/.test(e), 'patch signed with some other key: refused ("' + e.slice(0, 60) + '...")');
  e = await check(`${SP}/patch_good_tampered.zip`, `${SP}/patch_good_tampered.sig`); ok(/signature does not match/.test(e), 'patch changed after signing: refused');
  e = await check(`${SP}/patch_syntax.zip`, `${SP}/patch_syntax.sig`); ok(/PHP error/.test(e), 'signed patch with a PHP mistake: refused before writing anything');
  ok(build() === startBuild && !fs.existsSync(SITE + '/public_html/app/patched.txt'), 'nothing changed after the refusals');

  // ---- a signed patch whose database step fails restores everything by itself
  e = await check(`${SP}/patch_failmig.zip`, `${SP}/patch_failmig.sig`); ok(e === '' && /Apply update/.test(await p.locator('#found').innerText()), 'valid failing patch passes the check (it only fails while running)');
  await p.click('[data-act=applypatch]'); await p.waitForFunction(() => /undone automatically/.test(document.getElementById('applyErr').textContent), null, { timeout: 30000 });
  ok(true, 'failing patch: app says it was undone automatically'); ok(build() === startBuild && !fs.existsSync(SITE + '/public_html/app/patched.txt'), 'failing patch: files and build are exactly as before');
  await p.goto(B + '/app/updates.html'); await p.waitForSelector('.list .item'); ok(/Failed, restored/.test(await p.locator('.list').last().innerText()), 'failing patch is recorded in the history');

  // ---- sign the good patch with the browser signer, like the owner will
  const sp = await ctx.newPage(); await sp.goto('file:///home/user/Acf/tools/patch-signer.html'); await sp.setInputFiles('#k', `${SP}/keys/test.private.jwk.json`); await sp.waitForFunction(() => /Key code/.test(document.getElementById('fp').textContent));
  const keyCode = (await sp.locator('#fp b').innerText()); await sp.setInputFiles('#z', `${SP}/patch_good.zip`);
  const [dl] = await Promise.all([sp.waitForEvent('download'), sp.click('#go')]); const sigPath = `${SP}/patch_good_browser.sig`; await dl.saveAs(sigPath); await sp.close();
  await p.goto(B + '/app/updates.html'); await p.waitForSelector('#patchForm'); ok((await p.locator('.card').first().innerText()).includes(keyCode), 'key code in the signer (' + keyCode + ') matches the one the server trusts');

  // ---- apply
  e = await check(`${SP}/patch_good.zip`, sigPath); ok(e === '' && /Test good/.test(await p.locator('#found').innerText()), 'good patch signed in the browser passes the check');
  const found = await p.locator('#found').innerText(); ok(/add 3 files/.test(found) && /change 2 files|change 1 files/.test(found) || /add \d+ files/.test(found), 'summary lists what changes: ' + found.split('\n').filter(l => /add|change|remove/.test(l))[0]);
  ok(/database/i.test(found), 'summary warns about the database change');
  await p.click('[data-act=applypatch]'); await p.waitForFunction(() => /Updated\./.test(document.getElementById('found').innerText), null, { timeout: 30000 });
  ok(build() === 'TEST-BUILD-2', 'applied: build is now ' + build()); ok(await status('/app/patched.txt') === 200, 'applied: the new website file is live'); ok(table(), 'applied: the database change ran');
  const bk = fs.readdirSync(bdir); ok(bk.length === backupsBefore + 1, 'exactly one new backup folder was written (' + bk.length + ' now)');
  await p.reload(); await p.waitForSelector('.list .item');
  const hist = await p.locator('.card').last().innerText(); ok(/Applied/.test(hist) && /Undo this update/.test(hist), 'history shows it as applied with an Undo button');

  // ---- undo
  await p.click('[data-act=undo]'); await p.waitForFunction(() => /Undone/.test(document.body.innerText), null, { timeout: 30000 }); await p.waitForTimeout(500);
  ok(build() === startBuild, 'undone: build is back to ' + build()); ok(await status('/app/patched.txt') !== 200, 'undone: the new website file is gone'); ok(!table(), 'undone: the database change was rolled back');
  await p.reload(); await p.waitForSelector('.list .item'); const undoneId = (await p.locator('.list .item', { hasText: 'Test good' }).first().innerText()); ok(/Undone/.test(undoneId) && !/Undo this update/.test(undoneId), 'history shows the test patch as Undone, with no second undo for it');
  ok(errs.length === 0, 'no JS errors (' + errs.join('; ') + ')');

  // ---- other roles cannot see the page
  const c2 = await b.newContext(); const q = await c2.newPage(); await q.goto(B + '/app/login.html'); await q.fill('#lEmail', 'sara@alinsania.test'); await q.fill('#lPass', 'DemoPass#2026'); await Promise.all([q.waitForNavigation({ url: /(dashboard|setup)\.html/ }), q.click('button[type=submit]')]);
  await q.goto(B + '/app/updates.html'); await q.waitForTimeout(600); ok(/does not include/.test(await q.locator('#view').innerText()), 'Security Admin cannot open Updates');
  await b.close(); console.log(process.exitCode ? 'UPDATE CHECKS FAILED' : 'UPDATE CHECKS PASSED');
})().catch(e => { console.error('FAILED', e.message.split('\n').slice(0, 6).join(' | ')); process.exit(1); });
