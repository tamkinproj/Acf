import * as api from '../core/api.js';
import { assetUrl } from '../core/config.js';
import { setMeta } from '../core/db.js';
import { icon, mark, star } from '../core/icons.js';
import { busy, copyText, field, readForm, select, sheet, showErrors, toast } from '../core/ui.js';
import { $, $$, html, raw } from '../core/util.js';
import { setPin, validPin, verifyPin, pinBlocked } from '../auth/pin.js';
import { session, login, logout, reauthenticate, registerDevice, unlock } from '../auth/session.js';

// Everything shown before a person is inside the app. One layout: a calm emerald panel with the khatam lattice, and the form.

function layout({ title, lead, body, foot = '' }) {
  const b = session.get().branding;
  const name = b?.name || 'Foundation';
  const logo = b?.logo_hash ? html`<img class="mark-img" src="${assetUrl('assets/logo')}?v=${b.logo_hash}" alt="">` : mark();
  return html`
    <div class="auth">
      <section class="auth-art" aria-hidden="false">
        <div class="org">${logo}<b>${name}</b></div>
        <div><h2>Work that keeps going when the network doesn't.</h2>
          <p>Records are saved on this device first and synced when a connection is available.</p></div>
        <div class="pledge">
          <span>${icon('check')} Saved on this device the moment you press save</span>
          <span>${icon('sync')} Synced automatically; nothing is overwritten silently</span>
          <span>${icon('shield')} Every change is recorded in the activity log</span>
        </div>
      </section>
      <section class="auth-form"><div class="auth-card">
        <div class="brand-sm">${logo}</div>
        <div class="stack"><h1>${title}</h1>${lead ? html`<p class="muted">${lead}</p>` : ''}</div>
        ${body}${foot}
      </div></section>
    </div>`;
}

const alertBox = (kind, text) => html`<div class="banner ${kind}" role="alert">${icon(kind === 'bad' ? 'alert' : 'info')}<div class="grow">${text}</div></div>`;

// ---- sign in -------------------------------------------------------------------------------------------------
export function loginScreen(el) {
  const s = session.get();
  const offline = s.online === false || navigator.onLine === false;
  const conflict = s.conflictUser;
  el.innerHTML = layout({
    title: 'Sign in',
    lead: 'Use the account your administrator created for you.',
    body: html`
      ${conflict ? alertBox('warn', html`This device still holds <b>${conflict.waiting} unsynced ${conflict.waiting === 1 ? 'change' : 'changes'}</b> from <b>${conflict.name}</b>. Sign in as ${conflict.email} to sync them first.`) : ''}
      ${offline ? alertBox('warn', 'You appear to be offline. Signing in needs a connection.') : ''}
      <form class="form" data-login novalidate>
        <div data-msg></div>
        ${field({ label: 'Email', name: 'email', type: 'email', required: true, autocomplete: 'username', value: conflict?.email ?? '' })}
        ${field({ label: 'Password', name: 'password', type: 'password', required: true, autocomplete: 'current-password' })}
        <button class="btn" type="submit">Sign in</button>
      </form>
      ${s.hasPin && s.user ? html`<button class="btn ghost" type="button" data-pin>${icon('lock')} Unlock with PIN</button>` : ''}`,
  }).toString();

  const form = $('[data-login]', el);
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const { email, password } = readForm(form);
    const msg = $('[data-msg]', form);
    if (!email || !password) { msg.innerHTML = alertBox('bad', 'Enter your email and password.').toString(); return; }
    const btn = $('button[type=submit]', form);
    busy(btn, true, 'Signing in…');
    msg.innerHTML = '';
    try { await login(email, password); }
    catch (err) {
      busy(btn, false);
      const text = err.network ? 'Cannot reach the server. Check your connection and try again.'
        : err.status === 429 ? 'Too many attempts. Please wait a minute and try again.'
        : err.status === 401 ? 'The email or password is not correct.' : err.message;
      msg.innerHTML = alertBox('bad', text).toString();
      $('input[name=password]', form).value = '';
    }
  });
  $('[data-pin]', el)?.addEventListener('click', () => session.set({ status: 'locked' }));
}

// ---- device registration -------------------------------------------------------------------------------------
export async function deviceScreen(el) {
  const s = session.get();
  const canManage = s.permissions.includes('devices.manage');
  el.innerHTML = layout({
    title: 'Register this device',
    lead: 'Every browser or phone that keeps records offline is a registered device. This is how changes are traced to where they were made, and how access can be withdrawn if a device is lost.',
    body: html`<div class="stack" data-choices><div class="skeleton"></div><div class="skeleton"></div></div>`,
    foot: html`<button class="btn ghost" type="button" data-signout>${icon('logout')} Sign out</button>`,
  }).toString();
  $('[data-signout]', el).addEventListener('click', () => logout({ discard: true }));

  let unclaimed = null;
  if (s.permissions.includes('devices.view')) {
    try { unclaimed = (await api.get('/devices')).data.find((d) => d.is_primary && !d.claimed && !d.revoked); } catch { /* offline or no permission */ }
  }
  const box = $('[data-choices]', el);
  box.innerHTML = html`
    ${unclaimed && canManage ? html`<div class="card notch"><h3>Use this installation's device</h3>
      <p class="muted">${unclaimed.name} · <span class="mono">${unclaimed.device_code}</span> was created during setup and has not been claimed yet.</p>
      <p class="btn-row"><button class="btn" type="button" data-claim="${unclaimed.id}">Claim it for this browser</button></p></div>` : ''}
    ${canManage ? html`<div class="card"><h3>Register a new device</h3>
      <form class="form" data-new>${field({ label: 'Device name', name: 'name', required: true, value: '', hint: 'For example: Field phone – Cotabato' })}
        ${select({ label: 'Type', name: 'type', value: 'field', options: [['office', 'Office'], ['field', 'Field'], ['mobile', 'Mobile'], ['other', 'Other']] })}
        <div data-msg-new></div><button class="btn ghost" type="submit">Register</button></form></div>` : ''}
    <div class="card"><h3>I already have a device token</h3>
      <p class="muted">${canManage ? 'Paste a token an administrator gave you.' : 'Ask an administrator to register a device for you, then paste its token here.'}</p>
      <form class="form" data-paste>${field({ label: 'Device token', name: 'token', attrs: 'spellcheck="false" autocapitalize="off"' })}<div data-msg-paste></div><button class="btn ghost" type="submit">Use this token</button></form></div>`.toString();

  const finish = async (data) => {
    await setMeta('deviceInfo', { device_code: data.device_code, name: data.name });
    await registerDevice(data.token);
  };
  $('[data-claim]', box)?.addEventListener('click', async (e) => {
    try { finish((await api.post(`/devices/${e.currentTarget.dataset.claim}/claim`)).data); } catch (err) { toast(err.message, 'bad'); }
  });
  $('[data-new]', box)?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = readForm(e.currentTarget);
    try { await finish((await api.post('/devices', f)).data); } catch (err) { $('[data-msg-new]', box).innerHTML = alertBox('bad', err.message).toString(); }
  });
  $('[data-paste]', box).addEventListener('submit', async (e) => {
    e.preventDefault();
    const { token } = readForm(e.currentTarget);
    const msg = $('[data-msg-paste]', box);
    if (!token?.startsWith('fdt_')) { msg.innerHTML = alertBox('bad', 'Device tokens start with fdt_ — check what you pasted.').toString(); return; }
    api.setDeviceToken(token);
    try { const { data } = await api.get('/devices/current'); await finish({ ...data, token }); }
    catch (err) { api.setDeviceToken(null); msg.innerHTML = alertBox('bad', err.status === 401 ? 'That token is not valid, or was revoked.' : err.message).toString(); }
  });
}

// ---- lock screen (PIN, or password fallback) ---------------------------------------------------------------------
export function lockScreen(el) {
  const s = session.get();
  let digits = '';
  const render = (note = '', passwordMode = false) => {
    el.innerHTML = layout({
      title: 'Welcome back',
      lead: `${s.user?.name ?? ''} — this device is locked.`,
      body: passwordMode ? html`
        <form class="form" data-pw>${note ? alertBox('bad', note) : ''}${field({ label: 'Password', name: 'password', type: 'password', required: true, autocomplete: 'current-password' })}
          <button class="btn" type="submit">Unlock</button></form>
        ${s.hasPin ? html`<button class="btn ghost" type="button" data-usepin>Use PIN instead</button>` : ''}`
      : html`
        ${note ? alertBox('bad', note) : ''}
        <div class="pin-dots" aria-label="PIN entry">${[0, 1, 2, 3, 4, 5].map((i) => html`<i class="${i < digits.length ? 'on' : ''}"></i>`)}</div>
        <div class="keypad" role="group" aria-label="PIN keypad">${[1, 2, 3, 4, 5, 6, 7, 8, 9].map((n) => html`<button type="button" data-d="${n}">${n}</button>`)}
          <button type="button" data-back aria-label="Delete">⌫</button><button type="button" data-d="0">0</button><button type="button" data-ok aria-label="Unlock">${icon('check')}</button></div>
        <button class="btn ghost" type="button" data-usepw>Use my password instead</button>`,
      foot: html`<button class="btn ghost" type="button" data-signout>${icon('logout')} Sign out</button>`,
    }).toString();
    bind();
  };
  const submitPin = async () => {
    if (digits.length !== 6) return;
    const r = await verifyPin(digits);
    digits = '';
    if (r.ok) { unlock(); return; }
    render(r.blocked ? 'Too many wrong PINs. Use your password to unlock.' : `Wrong PIN. ${r.left} ${r.left === 1 ? 'try' : 'tries'} left.`, r.blocked);
  };
  const bind = () => {
    $$('[data-d]', el).forEach((b) => b.addEventListener('click', () => { if (digits.length < 6) { digits += b.dataset.d; $$('.pin-dots i', el).forEach((i, n) => i.classList.toggle('on', n < digits.length)); if (digits.length === 6) submitPin(); } }));
    $('[data-back]', el)?.addEventListener('click', () => { digits = digits.slice(0, -1); $$('.pin-dots i', el).forEach((i, n) => i.classList.toggle('on', n < digits.length)); });
    $('[data-ok]', el)?.addEventListener('click', submitPin);
    $('[data-usepw]', el)?.addEventListener('click', () => render('', true));
    $('[data-usepin]', el)?.addEventListener('click', () => render('', false));
    $('[data-signout]', el)?.addEventListener('click', async () => {
      const r = await logout();
      if (!r.ok && confirm(`${r.waiting} unsynced ${r.waiting === 1 ? 'change' : 'changes'} on this device will be lost if you sign out. Sign out anyway?`)) await logout({ discard: true });
    });
    $('[data-pw]', el)?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const { password } = readForm(e.currentTarget);
      try { await reauthenticate(password); unlock(); }
      catch (err) { render(err.network ? 'Cannot reach the server to check your password. Use your PIN, or reconnect.' : 'The password is not correct.', true); }
    });
  };
  const keys = (e) => { if (/^\d$/.test(e.key) && !$('[data-pw]', el)) { $(`[data-d="${e.key}"]`, el)?.click(); } else if (e.key === 'Backspace') $('[data-back]', el)?.click(); };
  document.addEventListener('keydown', keys);
  (async () => {
    if (!s.hasPin || (await pinBlocked())) render(s.hasPin ? 'Too many wrong PINs. Use your password to unlock.' : '', true); else render();
  })();
  return () => document.removeEventListener('keydown', keys);
}

// ---- forced password change ----------------------------------------------------------------------------------
export function passwordScreen(el, onDone) {
  el.innerHTML = layout({
    title: 'Choose a new password',
    lead: 'Your administrator gave you a temporary password. Pick one only you know.',
    body: html`<form class="form" data-pwchange novalidate><div data-msg></div>
      ${field({ label: 'Temporary password', name: 'current_password', type: 'password', required: true, autocomplete: 'current-password' })}
      ${field({ label: 'New password', name: 'password', type: 'password', required: true, autocomplete: 'new-password', hint: 'At least 10 characters, with letters and numbers.' })}
      ${field({ label: 'Repeat new password', name: 'password_confirmation', type: 'password', required: true, autocomplete: 'new-password' })}
      <button class="btn" type="submit">Save password</button></form>`,
    foot: html`<button class="btn ghost" type="button" data-signout>${icon('logout')} Sign out</button>`,
  }).toString();
  $('[data-signout]', el).addEventListener('click', () => logout({ discard: true }));
  const form = $('[data-pwchange]', el);
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $('button[type=submit]', form);
    busy(btn, true, 'Saving…');
    try { await api.put('/auth/password', readForm(form)); toast('Password changed'); onDone(); }
    catch (err) {
      busy(btn, false);
      if (!showErrors(form, err.errors)) $('[data-msg]', form).innerHTML = alertBox('bad', err.message).toString();
    }
  });
}

// ---- PIN setup (offered after sign-in) -----------------------------------------------------------------------
export function pinSetupSheet({ onDone } = {}) {
  sheet({
    title: 'Protect this device',
    body: html`<form class="form" data-pinform novalidate>
      <p class="muted">This device keeps records offline. A 6-digit PIN locks it when you step away, and lets you back in without a connection. Only a scrambled check of the PIN is stored.</p>
      <div data-msg></div>
      ${field({ label: 'New PIN (6 digits)', name: 'pin', type: 'password', autocomplete: 'off', attrs: 'inputmode="numeric" pattern="[0-9]*" maxlength="6"' })}
      ${field({ label: 'Repeat PIN', name: 'pin2', type: 'password', autocomplete: 'off', attrs: 'inputmode="numeric" pattern="[0-9]*" maxlength="6"' })}
      <div class="btn-row"><button class="btn" type="submit">Set PIN</button><button class="btn ghost" type="button" data-close>Not now</button></div></form>`,
    onMount: (el, close) => {
      const form = $('[data-pinform]', el);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const { pin, pin2 } = readForm(form);
        const msg = $('[data-msg]', form);
        if (pin !== pin2) { msg.innerHTML = alertBox('bad', 'The two PINs do not match.').toString(); return; }
        if (!validPin(pin ?? '')) { msg.innerHTML = alertBox('bad', 'Use 6 digits that are not all the same or a simple sequence like 123456.').toString(); return; }
        await setPin(pin);
        session.set({ hasPin: true });
        toast('PIN set');
        close(true);
        onDone?.();
      });
    },
  });
}
export { alertBox };
