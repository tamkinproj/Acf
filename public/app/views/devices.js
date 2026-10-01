import * as api from '../core/api.js';
import { db } from '../core/db.js';
import { icon } from '../core/icons.js';
import { confirmDialog, copyText, field, readForm, select, sheet, showErrors, toast, busy } from '../core/ui.js';
import { $, ago, html } from '../core/util.js';
import { kick } from '../sync/engine.js';
import { kit } from './kit.js';

// Devices: every browser or phone allowed to sync. Online status and token actions come from the server, so they need a
// connection; the list itself is read from the local copy and works offline.
const TYPES = [['office', 'Office computer'], ['field', 'Field device'], ['mobile', 'Phone or tablet'], ['server', 'Local server'], ['other', 'Other']];

export default {
  async mount(ctx) {
    const k = kit(ctx);
    const manage = ctx.can('devices.manage');
    let devices = [];
    let live = new Map();
    let thisDevice = null;
    const draw = () => k.render(view(devices, live, thisDevice, manage));
    k.live(() => db.devices.filter((d) => !d.deleted_at).toArray(), (r) => { devices = r.sort((a, b) => Number(b.is_primary) - Number(a.is_primary) || a.name.localeCompare(b.name)); draw(); });
    const refresh = () => api.get('/devices').then(({ data }) => { live = new Map(data.map((d) => [d.id, d])); draw(); }).catch(() => {});
    refresh();
    api.get('/devices/current').then(({ data }) => { thisDevice = data.id; draw(); }).catch(() => {});

    k.on('click', '[data-add]', () => openCreate(refresh));
    k.on('click', '[data-rename]', (e, t) => openRename(devices.find((d) => d.id === t.dataset.rename), refresh));
    k.on('click', '[data-rotate]', async (e, t) => {
      const d = devices.find((x) => x.id === t.dataset.rotate);
      if (!(await confirmDialog({ title: `New token for ${d.name}?`, text: 'The old token stops working immediately. That device must be set up again with the new one.', confirmLabel: 'Create new token' }))) return;
      try { const { data } = await api.post(`/devices/${d.id}/rotate-token`); tokenSheet(d.name, data.token); } catch (err) { toast(api.explain(err), 'bad'); }
    });
    k.on('click', '[data-revoke]', async (e, t) => {
      const d = devices.find((x) => x.id === t.dataset.revoke);
      if (!(await confirmDialog({ title: `Revoke ${d.name}?`, text: 'It can no longer sync. Changes it has not sent yet will be refused. This cannot be undone.', confirmLabel: 'Revoke device', danger: true }))) return;
      try { await api.post(`/devices/${d.id}/revoke`); kick(0); refresh(); toast('Device revoked'); } catch (err) { toast(api.explain(err), 'bad'); }
    });
    return () => k.cleanup();
  },
};

function tokenSheet(name, token) {
  sheet({
    title: 'Device token',
    body: html`<div class="stack"><p>Enter this token on <b>${name}</b> when it asks to be set up.</p>
      <div class="secret"><code>${token}</code><button class="btn sm ghost" type="button" data-copy>${icon('copy')} Copy</button></div>
      <div class="banner warn">${icon('alert')}<div class="grow">Shown only once. Anyone holding it can sync as that device, so share it privately.</div></div>
      <div class="btn-row"><button class="btn" type="button" data-close>Done</button></div></div>`,
    onMount: (el) => $('[data-copy]', el).addEventListener('click', () => copyText(token)),
  });
}

function openCreate(refresh) {
  sheet({
    title: 'Register a device',
    body: html`<form class="form" novalidate>
      <div class="banner info">${icon('cloud')}<div class="grow">Needs a connection. You get a one-time token to enter on the new device.</div></div>
      ${field({ label: 'Name', name: 'name', required: true, hint: 'For example “Field phone — Cotabato”.' })}
      ${select({ label: 'Kind', name: 'type', options: TYPES })}
      <div class="btn-row"><button class="btn" type="submit">Register</button><button class="btn ghost" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Registering…');
      try { const { data } = await api.post('/devices', readForm(form)); close(); kick(0); refresh(); tokenSheet(data.name, data.token); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
    }),
  });
}

function openRename(d, refresh) {
  sheet({
    title: 'Rename device',
    body: html`<form class="form" novalidate>${field({ label: 'Name', name: 'name', value: d.name, required: true })}
      <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn ghost" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api.patch(`/devices/${d.id}`, readForm(e.currentTarget)); close(); kick(0); refresh(); toast('Renamed'); } catch (err) { if (!showErrors(e.currentTarget, err.errors)) toast(api.explain(err), 'bad'); }
    }),
  });
}

function view(devices, live, thisDevice, manage) {
  return html`
    <div class="page-head"><div><h2>Devices</h2><p>Phones, tablets and computers allowed to work with this foundation's data.</p></div>
      ${manage ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} Register device</button></div>` : ''}</div>
    ${devices.length ? html`<div class="stack">${devices.map((d) => {
      const s = live.get(d.id) ?? d;
      const revoked = !!(s.revoked ?? d.revoked_at);
      const known = live.has(d.id);
      const state = revoked ? ['red', 'Revoked'] : !known ? ['grey', 'Status needs a connection'] : !s.claimed ? ['amber', 'Not set up yet'] : s.online ? ['', 'Online'] : ['grey', 'Offline'];
      return html`<div class="card"><div class="card-head"><div class="row"><span class="avatar sq" aria-hidden="true">${icon('phone')}</span><div>
        <h3>${d.name}${d.id === thisDevice ? html` <span class="chip gold">This device</span>` : ''}${d.is_primary ? html` <span class="chip grey">Installation</span>` : ''}</h3>
        <p class="muted">${TYPES.find(([k]) => k === d.type)?.[1] ?? d.type} · <span class="mono">${d.device_code}</span></p></div></div>
        <span class="chip ${state[0]}">${state[1]}</span></div>
        <dl class="kv"><dt>Last seen</dt><dd>${(s.last_seen_at ?? d.last_seen_at) ? ago(s.last_seen_at ?? d.last_seen_at) : 'Never'}</dd><dt>App version</dt><dd>${d.app_version || '—'}</dd></dl>
        ${manage && !revoked ? html`<div class="btn-row"><button class="btn sm ghost" type="button" data-rename="${d.id}">${icon('edit')} Rename</button>
          <button class="btn sm ghost" type="button" data-rotate="${d.id}">${icon('rotate')} New token</button>
          ${d.is_primary ? '' : html`<button class="btn sm danger" type="button" data-revoke="${d.id}">Revoke</button>`}</div>` : ''}</div>`;
    })}</div>` : html`<div class="empty">${icon('phone')}<b>No devices yet</b><span>Devices appear after the first sync.</span></div>`}`;
}
