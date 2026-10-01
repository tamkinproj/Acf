import * as api from '../core/api.js';
import { db } from '../core/db.js';
import { icon } from '../core/icons.js';
import { getTheme, setTheme } from '../core/theme.js';
import { busy, confirmDialog, field, readForm, select, showErrors, toast } from '../core/ui.js';
import { $, ago, html, raw } from '../core/util.js';
import { clearPin } from '../auth/pin.js';
import { logout, refreshProfile, session } from '../auth/session.js';
import { APP_VERSION } from '../core/config.js';
import { kit } from './kit.js';
import { pinSetupSheet } from './auth.js';

// My account: profile, password, device PIN, other places I am signed in, appearance, and the safe way to sign out.
export default {
  async mount(ctx) {
    const k = kit(ctx);
    let sessions = null;
    let sessionsError = '';
    const draw = () => k.render(view(session.get(), sessions, sessionsError));
    const loadSessions = () => api.get('/auth/sessions').then(({ data }) => { sessions = data; sessionsError = ''; draw(); }).catch((e) => { sessionsError = e.network ? 'Needs a connection.' : api.explain(e); draw(); });
    const off = session.subscribe(draw);
    loadSessions();

    k.on('submit', '[data-profile]', async (e, form) => {
      e.preventDefault();
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Saving…');
      try { await api.patch('/auth/profile', readForm(form)); await refreshProfile(); toast('Profile saved'); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
    });
    k.on('submit', '[data-password]', async (e, form) => {
      e.preventDefault();
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Changing…');
      try { await api.put('/auth/password', readForm(form)); form.reset(); showErrors(form, {}); toast('Password changed. Other devices were signed out.'); loadSessions(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
    });
    k.on('click', '[data-pin-set]', () => pinSetupSheet({ onDone: () => session.set({ hasPin: true }) }));
    k.on('click', '[data-pin-off]', async () => {
      if (await confirmDialog({ title: 'Remove the PIN?', text: 'This device will no longer lock itself, and you will need your password to get back in after an idle lock.', confirmLabel: 'Remove PIN', danger: true })) { await clearPin(); session.set({ hasPin: false }); toast('PIN removed'); }
    });
    k.on('change', '[data-theme]', (e, t) => setTheme(t.value));
    k.on('click', '[data-revoke]', async (e, t) => {
      try { await api.del(`/auth/sessions/${t.dataset.revoke}`); toast('Signed out there'); loadSessions(); } catch (err) { toast(api.explain(err), 'bad'); }
    });
    k.on('click', '[data-signout]', async () => {
      const r = await logout();
      if (r.ok) return;
      const go = await confirmDialog({ title: 'Sign out and lose changes?', text: `${r.waiting} ${r.waiting === 1 ? 'change has' : 'changes have'} not reached the server yet. Signing out now would discard ${r.waiting === 1 ? 'it' : 'them'} for good. Connect and sync first if you can.`, confirmLabel: 'Discard and sign out', danger: true, cancelLabel: 'Stay signed in' });
      if (go) await logout({ discard: true });
    });
    return () => { off(); k.cleanup(); };
  },
};

const device = (ua) => {
  if (!ua) return 'Unknown browser';
  const os = /Android/.test(ua) ? 'Android' : /iPhone|iPad/.test(ua) ? 'iOS' : /Windows/.test(ua) ? 'Windows' : /Mac OS/.test(ua) ? 'Mac' : /Linux/.test(ua) ? 'Linux' : 'Device';
  const br = /Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : 'Browser';
  return `${br} on ${os}`;
};

function view(s, sessions, sessionsError) {
  const u = s.user;
  return html`
    <div class="page-head"><div><h2>My account</h2><p>${u.email} · ${u.role?.name ?? ''}</p></div></div>
    <div class="grid-2">
      <section class="card"><div class="card-head"><h3>Profile</h3></div>
        <form class="form" data-profile novalidate>
          ${field({ label: 'Full name', name: 'name', value: u.name, required: true, autocomplete: 'name' })}
          ${field({ label: 'Phone', name: 'phone', type: 'tel', value: u.phone, autocomplete: 'tel' })}
          ${select({ label: 'Language', name: 'locale', value: u.locale ?? 'en', options: [['en', 'English'], ['fil', 'Filipino'], ['ar', 'Arabic']] })}
          <div class="btn-row"><button class="btn" type="submit">Save profile</button></div></form></section>

      <section class="card"><div class="card-head"><h3>Password</h3></div>
        <form class="form" data-password novalidate>
          ${field({ label: 'Current password', name: 'current_password', type: 'password', autocomplete: 'current-password', required: true })}
          ${field({ label: 'New password', name: 'password', type: 'password', autocomplete: 'new-password', required: true, hint: 'At least 12 characters.' })}
          ${field({ label: 'Repeat new password', name: 'password_confirmation', type: 'password', autocomplete: 'new-password', required: true })}
          <div class="btn-row"><button class="btn" type="submit">Change password</button></div></form></section>

      <section class="card"><div class="card-head"><h3>This device</h3><span class="chip ${s.hasPin ? '' : 'amber'}">${s.hasPin ? 'PIN on' : 'No PIN'}</span></div>
        <p class="muted">A 6-digit PIN locks this device when idle and lets you back in without a connection.</p>
        <div class="btn-row"><button class="btn ghost" type="button" data-pin-set>${icon('lock')} ${s.hasPin ? 'Change PIN' : 'Set a PIN'}</button>
          ${s.hasPin ? html`<button class="btn ghost" type="button" data-pin-off>Remove PIN</button>` : ''}</div>
        <hr>${raw(select({ label: 'Appearance', name: 'theme', value: getTheme(), options: [['auto', 'Follow this device'], ['light', 'Light'], ['dark', 'Dark']] }).toString().replace('<select', '<select data-theme'))}</section>

      <section class="card"><div class="card-head"><h3>Where I'm signed in</h3></div>
        ${sessionsError ? html`<p class="muted">${sessionsError}</p>` : sessions === null ? html`<p class="muted">Loading…</p>`
          : sessions.length ? html`<ul class="list">${sessions.map((x) => html`<li><div class="grow"><div class="t">${device(x.agent)} ${x.current ? html`<span class="chip">This one</span>` : ''}</div><div class="d">${x.ip || ''} · active ${ago(x.last_active)}</div></div>
            ${x.current ? '' : html`<button class="btn sm ghost" type="button" data-revoke="${x.handle}">Sign out</button>`}</li>`)}</ul>`
          : html`<p class="muted">No other sessions.</p>`}</section>
    </div>
    <section class="card"><div class="card-head"><h3>Sign out</h3></div>
      <p class="muted">Signing out removes this foundation's data from this browser. If you have changes that have not synced, you will be warned first.</p>
      <div class="btn-row"><button class="btn danger" type="button" data-signout>${icon('logout')} Sign out</button></div>
      <p class="hint">Foundation Management System · version ${APP_VERSION}</p></section>`;
}
