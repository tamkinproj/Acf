const { chromium } = require('playwright'); const fs = require('fs'); const { execSync } = require('child_process'); const B = 'http://127.0.0.1:8090', SP = process.argv[2], SITE = '/srv/apxtest/site';
const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };
const build = () => fs.readFileSync(SITE + '/insania_app/BUILD', 'utf8').trim();
(async () => { const b = await chromium.launch(); const ctx = await b.newContext({ acceptDownloads: true, viewport: { width: 390, height: 844 } }); const p = await ctx.newPage(); p.on('dialog', d => d.accept());
  const start = build();
  // sign in the browser, producing the ONE-FILE update
  const sp = await ctx.newPage(); await sp.goto('file:///home/user/Acf/tools/patch-signer.html'); await sp.setInputFiles('#k', `${SP}/keys/test.private.jwk.json`); await sp.waitForFunction(() => /Key code/.test(document.getElementById('fp').textContent)); await sp.setInputFiles('#z', `${SP}/patch_good.zip`);
  const [dl] = await Promise.all([sp.waitForEvent('download'), sp.click('#go2')]); const bundle = `${SP}/patch_good.update.zip`; await dl.saveAs(bundle); await sp.close();
  ok(execSync(`unzip -l ${bundle}`).toString().includes('patch.zip') && execSync(`unzip -l ${bundle}`).toString().includes('signature.sig') && execSync(`unzip -tq ${bundle}`).toString().includes('No errors'), 'the signer made a valid one-file update (zip test passes)');
  await p.goto(B + '/app/login.html'); await p.fill('#lEmail', 'admin@alinsania.test'); await p.fill('#lPass', 'Str0ng#Passw0rd'); await Promise.all([p.waitForNavigation({ url: /(dashboard|setup)\.html/ }), p.click('button[type=submit]')]);
  await p.goto(B + '/app/updates.html'); await p.waitForSelector('#autoBtn');
  // nothing chosen -> asked to choose
  await p.click('#autoBtn'); ok(/Choose the update file/.test(await p.locator('#formErr').innerText()), 'no file chosen: asks for one');
  // a plain patch with no signature is refused
  await p.setInputFiles('#pz', `${SP}/patch_good.zip`); await p.click('#autoBtn'); await p.waitForFunction(() => /no signature/.test(document.getElementById('formErr').textContent), null, { timeout: 15000 }); ok(build() === start, 'a zip with no signature: refused, nothing changed');
  // the one-file update: one click, updates by itself
  await p.setInputFiles('#pz', bundle); await p.click('#autoBtn'); await p.waitForFunction(() => /Updated to/.test(document.getElementById('found').innerText), null, { timeout: 40000 });
  ok(build() === 'TEST-ONE-STEP', 'one upload, one click: the system updated itself (build ' + build() + ')'); ok((await (await fetch(B + '/app/patched.txt')).text()).includes('one step ok'), 'the new website file is live');
  await p.reload(); await p.waitForSelector('.list .item'); ok(/Applied/.test(await p.locator('.card').last().innerText()) && /Undo this update/.test(await p.locator('.card').last().innerText()), 'history shows it, with Undo');
  await p.click('[data-act=undo]'); await p.waitForFunction(() => /Undone/.test(document.body.innerText), null, { timeout: 30000 }); ok(build() === start, 'undo brings the previous build back');
  await b.close(); console.log(process.exitCode ? 'ONE-STEP CHECKS FAILED' : 'ONE-STEP CHECKS PASSED'); })().catch(e => { console.error('FAILED', e.message.split('\n').slice(0, 5).join(' | ')); process.exit(1); });
