import { db } from '../core/db.js';
import { icon } from '../core/icons.js';
import { setDisplayTimezone } from '../core/util.js';
import { select, toast, field } from '../core/ui.js';
import { html, raw } from '../core/util.js';
import { UNSYNCED, updateRecord } from '../sync/outbox.js';
import { kit } from './kit.js';

// System settings. The server declares every key; this map only decides how each one is shown. A key the client does not
// know yet (added by a newer module) still appears, as plain text, so nothing is ever hidden.
const META = {
  'app.name': { label: 'System name', hint: 'Shown in the sidebar and sign-in page when no foundation name is set.', type: 'text' },
  'app.timezone': { label: 'Time zone', hint: 'How dates and times are displayed. Records are stored in UTC.', type: 'text', list: 'tz' },
  'app.locale': { label: 'Language', hint: 'Default language for new accounts.', type: 'select', options: [['en', 'English'], ['fil', 'Filipino'], ['ar', 'Arabic']] },
  'app.currency': { label: 'Currency', hint: 'Used by future money-related modules.', type: 'select', options: ['PHP', 'USD', 'SAR', 'AED', 'EUR', 'GBP', 'MYR', 'IDR', 'SGD'] },
  'deployment.model': { label: 'Deployment model', hint: 'How devices relate to the central server.', type: 'select', options: [['central', 'One central server'], ['hybrid', 'Central server with local hubs'], ['local', 'Local only']] },
  'security.idle_lock_minutes': { label: 'Lock after idle (minutes)', hint: 'Applies on next sign-in.', type: 'number', min: 1, max: 480 },
  'sync.auto_interval_seconds': { label: 'Sync every (seconds)', hint: 'How often devices check for changes while online.', type: 'number', min: 15, max: 3600 },
};
const GROUPS = { general: 'General', security: 'Security', sync: 'Synchronization', system: 'System' };

export default {
  async mount(ctx) {
    const k = kit(ctx);
    const manage = ctx.can('settings.manage');
    let rows = [];
    let pending = new Set();
    const draw = () => k.render(view(rows, pending, manage));
    const order = Object.keys(META);
    const rank = (key) => (order.includes(key) ? order.indexOf(key) : order.length);
    k.live(() => db.settings.filter((s) => !s.deleted_at).toArray(), (r) => { rows = r.sort((a, b) => rank(a.key) - rank(b.key) || a.key.localeCompare(b.key)); draw(); });
    k.live(() => db.outbox.where('entity').equals('settings').filter((e) => UNSYNCED.includes(e.status)).toArray(), (r) => { pending = new Set(r.map((e) => e.entity_id)); draw(); });

    k.on('change', '[data-key]', async (e, t) => {
      const row = rows.find((r) => r.key === t.dataset.key);
      const meta = META[row.key];
      let value = t.value.trim();
      if (meta?.type === 'number') {
        value = Number(value);
        if (!Number.isInteger(value) || value < meta.min || value > meta.max) { toast(`Enter a whole number from ${meta.min} to ${meta.max}.`, 'bad'); t.value = row.value; return; }
      }
      if (value === '' || value === row.value) return;
      await updateRecord('settings', row.id, { value });
      if (row.key === 'app.timezone') setDisplayTimezone(value);
      toast('Saved on this device. It will sync when connected.');
    });
    return () => k.cleanup();
  },
};

function control(row, manage) {
  const m = META[row.key] ?? { type: 'text' };
  const label = m.label ?? row.key;
  const attrs = `data-key="${row.key}"`;
  if (m.type === 'select') return raw(select({ label, name: row.key.replace(/\./g, '_'), value: row.value, options: m.options, hint: m.hint, disabled: !manage }).toString().replace('<select', `<select ${attrs}`));
  const extra = m.type === 'number' ? `inputmode="numeric" min="${m.min}" max="${m.max}" ${attrs}` : `${attrs}${m.list ? ' list="tz"' : ''}`;
  return field({ label, name: row.key.replace(/\./g, '_'), type: m.type === 'number' ? 'number' : 'text', value: row.value, hint: m.hint, attrs: extra, readonly: !manage });
}

function view(rows, pending, manage) {
  const groups = {};
  for (const r of rows) (groups[r.group || 'general'] ||= []).push(r);
  const tz = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];
  return html`
    <div class="page-head"><div><h2>Settings</h2><p>${manage ? 'Changes save on this device straight away and reach everyone after syncing.' : 'You can see the settings but not change them.'}</p></div></div>
    ${rows.length ? Object.entries(groups).map(([g, list]) => html`<section class="stack"><h3>${GROUPS[g] ?? g}</h3>
      <div class="card form">${list.map((r) => html`<div class="setting">${control(r, manage)}${pending.has(r.id) ? html`<span class="chip amber">Waiting to sync</span>` : ''}</div>`)}</div></section>`)
      : html`<div class="empty">${icon('sliders')}<b>Settings not loaded yet</b><span>They arrive with the first sync.</span></div>`}
    <datalist id="tz">${tz.map((z) => html`<option value="${z}"></option>`)}</datalist>`;
}
