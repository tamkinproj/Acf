import { db } from '../core/db.js';
import { searchInput } from '../core/ui.js';
import { icon } from '../core/icons.js';
import { $, fmtDateTime, html } from '../core/util.js';
import { kit } from './kit.js';

// Activity: the audit trail, read from the local copy (so it works offline). Events made on this device appear
// immediately; the rest arrive with each sync. Nothing here can be edited or removed.
const AREAS = [['all', 'Everything'], ['auth', 'Sign-ins'], ['user', 'People'], ['role', 'Roles'], ['device', 'Devices'], ['location', 'Places'], ['setting', 'Settings'], ['foundation', 'Foundation'], ['sync', 'Sync']];
const PAGE = 50;

export default {
  async mount(ctx) {
    const k = kit(ctx);
    let rows = [];
    let deviceNames = new Map();
    let area = 'all';
    let q = '';
    let limit = PAGE;
    let detail = null;
    const filtered = () => {
      const term = q.toLowerCase();
      return rows.filter((r) => (area === 'all' || r.action.startsWith(`${area}.`)) && (!term || `${r.summary} ${r.user_name ?? ''}`.toLowerCase().includes(term)));
    };
    const draw = () => {
      const list = filtered();
      k.render(view(list.slice(0, limit), list.length, area, q, detail, deviceNames));
      const input = ctx.root.querySelector('#q');
      if (q && input) { input.focus(); input.setSelectionRange(q.length, q.length); }
    };
    k.live(() => db.devices.toArray(), (r) => { deviceNames = new Map(r.map((d) => [d.id, d.name])); draw(); });
    k.live(() => db.audit_logs.orderBy('occurred_at').reverse().limit(2000).toArray(), (r) => { rows = r; draw(); });
    k.on('input', '#q', (e, t) => { q = t.value; limit = PAGE; draw(); });
    k.on('change', '#area', (e, t) => { area = t.value; limit = PAGE; draw(); });
    k.on('click', '[data-more]', () => { limit += PAGE; draw(); });
    k.on('click', '[data-detail]', (e, t) => { detail = detail === t.dataset.detail ? null : t.dataset.detail; draw(); });
    return () => k.cleanup();
  },
};

const tone = (a) => (a.endsWith('.failed') || a.includes('revoke') || a.endsWith('.deleted') ? 'red' : a.endsWith('.created') || a === 'auth.login' ? 'green' : 'grey');
const show = (v) => (v === null || v === undefined ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v));

function changes(r) {
  const keys = [...new Set([...Object.keys(r.old_values || {}), ...Object.keys(r.new_values || {})])].filter((x) => !['updated_at', 'version'].includes(x));
  if (!keys.length) return html`<p class="muted">No field details were recorded.</p>`;
  return html`<dl class="kv">${keys.map((key) => html`<dt>${key.replace(/_/g, ' ')}</dt><dd>${r.old_values && key in r.old_values ? html`<s class="muted">${show(r.old_values[key])}</s> → ` : ''}${show((r.new_values || {})[key])}</dd>`)}</dl>`;
}

function view(list, total, area, q, detail, deviceNames) {
  return html`
    <div class="page-head"><div><h1>Activity</h1><p>A permanent record of who did what, and from which device.</p></div></div>
    <div class="card flush"><div class="filters">
      <div class="grow">${searchInput({ value: q, placeholder: 'Search activity', label: 'Search activity' })}</div>
      <div class="field"><label class="sr-only" for="area">Area</label><select class="select" id="area">${AREAS.map(([v, t]) => html`<option value="${v}" ${area === v ? 'selected' : ''}>${t}</option>`)}</select></div></div>
      ${list.length ? html`<ul class="list timeline">${list.map((r) => html`<li class="${detail === r.id ? 'open' : ''}">
        <span class="dot ${tone(r.action)}" aria-hidden="true"></span>
        <div class="grow"><div class="t">${r.summary}</div><div class="d">${r.user_name || 'System'} · ${fmtDateTime(r.occurred_at)}${deviceNames.get(r.device_id) ? ` · ${deviceNames.get(r.device_id)}` : ''}</div>
          ${detail === r.id ? html`<div class="detail">${changes(r)}<p class="muted mono">${r.action}</p></div>` : ''}</div>
        <button class="btn sm ghost" type="button" data-detail="${r.id}" aria-expanded="${detail === r.id}" aria-label="Details">${icon('chevron')}</button></li>`)}</ul>
        ${total > list.length ? html`<div class="btn-row center"><button class="btn secondary" type="button" data-more>Show more (${total - list.length} left)</button></div>` : ''}`
        : html`<div class="empty">${icon('list')}<b>${q || area !== 'all' ? 'Nothing matches' : 'No activity yet'}</b><span>${q || area !== 'all' ? 'Try a different search or area.' : 'Events appear as people use the system.'}</span></div>`}</div>`;
}
