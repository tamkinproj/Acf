import { db } from '../core/db.js';
import { icon } from '../core/icons.js';
import { confirmDialog, field, readForm, select, sheet, toast } from '../core/ui.js';
import { $, html, raw } from '../core/util.js';
import { createRecord, removeRecord, updateRecord, UNSYNCED } from '../sync/outbox.js';
import { kit } from './kit.js';

// Places: the geographic structure every future activity will point at (country > region > province > municipality >
// barangay > site). Add, edit and delete all work offline; a dot marks anything that has not reached the server yet.
const LEVELS = [['country', 'Country'], ['region', 'Region'], ['province', 'Province'], ['municipality', 'Municipality / City'], ['barangay', 'Barangay'], ['site', 'Specific site']];
const RANK = Object.fromEntries(LEVELS.map(([k], i) => [k, i + 1]));
const LABEL = Object.fromEntries(LEVELS);

export default {
  async mount(ctx) {
    const k = kit(ctx);
    const manage = ctx.can('locations.manage');
    let places = [];
    let pending = new Set();
    let q = '';
    const byId = () => new Map(places.map((p) => [p.id, p]));

    const draw = () => {
      const tree = flatten(places, q);
      k.render(html`
        <div class="page-head"><div><h2>Places</h2><p>Where the foundation works. Every beneficiary, distribution and project will point to one of these.</p></div>
          ${manage ? html`<div class="btn-row"><button class="btn" type="button" data-add="">${icon('plus')} Add place</button></div>` : ''}</div>
        <div class="card">
          <div class="field"><label class="sr-only" for="q">Search places</label><input class="input" id="q" type="search" placeholder="Search places…" value="${q}" autocomplete="off"></div>
          ${tree.length ? html`<ul class="tree">${tree.map(({ p, depth }) => row(p, depth, pending.has(p.id), manage))}</ul>`
            : html`<div class="empty">${icon('pin')}<b>${q ? 'No places match' : 'No places yet'}</b><span>${q ? 'Try a different name.' : manage ? 'Start with your country or region, then add what is inside it.' : 'Places will appear here once they are added.'}</span>
              ${!q && manage ? html`<button class="btn" type="button" data-add="">${icon('plus')} Add the first place</button>` : ''}</div>`}
        </div>`);
      const input = ctx.root.querySelector('#q');
      if (q) { input.focus(); input.setSelectionRange(q.length, q.length); }
    };

    k.live(() => db.locations.filter((l) => !l.deleted_at).toArray(), (rows) => { places = rows; draw(); });
    k.live(() => db.outbox.where('entity').equals('locations').filter((e) => UNSYNCED.includes(e.status)).toArray(), (rows) => { pending = new Set(rows.map((e) => e.entity_id)); draw(); });

    k.on('input', '#q', (e, t) => { q = t.value; draw(); });
    k.on('click', '[data-add]', (e, t) => openForm({ parentId: t.dataset.add || null }));
    k.on('click', '[data-edit]', (e, t) => openForm({ id: t.dataset.edit }));
    k.on('click', '[data-del]', async (e, t) => {
      const p = byId().get(t.dataset.del);
      if (places.some((c) => c.parent_id === p.id)) { toast('Move or remove the places inside it first.', 'bad'); return; }
      if (await confirmDialog({ title: `Remove “${p.name}”?`, text: 'It disappears for everyone after syncing. This is recorded in the activity log.', confirmLabel: 'Remove', danger: true })) {
        await removeRecord('locations', p.id);
        toast('Removed. It will sync when connected.');
      }
    });

    function openForm({ id = null, parentId = null }) {
      const existing = id ? byId().get(id) : null;
      const parent = byId().get(existing ? existing.parent_id : parentId);
      const minRank = parent ? RANK[parent.level] + 1 : 1;
      const levels = LEVELS.filter(([key]) => RANK[key] >= minRank);
      const parentOptions = [['', '— Top level —'], ...places.filter((p) => p.id !== id && !isInside(p, id, byId())).sort((a, b) => a.name.localeCompare(b.name)).map((p) => [p.id, `${'· '.repeat(depthOf(p, byId()))}${p.name}`])];
      sheet({
        title: existing ? 'Edit place' : parent ? `Add inside ${parent.name}` : 'Add place',
        body: html`<form class="form" data-form novalidate>
          ${field({ label: 'Name', name: 'name', value: existing?.name, required: true })}
          <div class="row-2">
            ${select({ label: 'Inside', name: 'parent_id', value: existing ? existing.parent_id ?? '' : parentId ?? '', options: parentOptions })}
            ${select({ label: 'Type', name: 'level', value: existing?.level ?? levels[0]?.[0], options: LEVELS })}
          </div>
          <div class="row-2">${field({ label: 'Code (optional)', name: 'code', value: existing?.code })}
            <div class="field"><span class="lbl">Status</span><label class="switch"><input type="checkbox" name="is_active" ${raw(existing ? (existing.is_active ? 'checked' : '') : 'checked')}> Active</label></div></div>
          <div class="row-2">${field({ label: 'Latitude (optional)', name: 'latitude', type: 'number', value: existing?.latitude, attrs: 'step="any" min="-90" max="90"' })}
            ${field({ label: 'Longitude (optional)', name: 'longitude', type: 'number', value: existing?.longitude, attrs: 'step="any" min="-180" max="180"' })}</div>
          <div data-msg class="err" hidden></div>
          <div class="btn-row"><button class="btn" type="submit">${existing ? 'Save' : 'Add place'}</button><button class="btn ghost" type="button" data-close>Cancel</button></div></form>`,
        onMount: (el, close) => {
          const form = $('[data-form]', el);
          const syncLevels = () => {
            const p = byId().get(form.parent_id.value);
            const min = p ? RANK[p.level] + 1 : 1;
            for (const o of form.level.options) o.disabled = RANK[o.value] < min;
            if (RANK[form.level.value] < min) form.level.value = LEVELS.find(([key]) => RANK[key] >= min)?.[0] ?? 'site';
          };
          form.parent_id.addEventListener('change', syncLevels); syncLevels();
          form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const f = readForm(form);
            const msg = $('[data-msg]', form);
            if (!f.name) { msg.hidden = false; msg.textContent = 'Give the place a name.'; return; }
            const fields = { name: f.name, level: f.level, parent_id: f.parent_id || null, code: f.code ?? null, is_active: !!f.is_active,
              latitude: f.latitude === null ? null : Number(f.latitude), longitude: f.longitude === null ? null : Number(f.longitude) };
            if (existing) {
              const changed = Object.fromEntries(Object.entries(fields).filter(([key, v]) => (existing[key] ?? null) !== v));
              if (Object.keys(changed).length) await updateRecord('locations', existing.id, changed);
            } else {
              await createRecord('locations', fields);
            }
            close(); toast(existing ? 'Saved' : 'Place added');
          });
        },
      });
    }
    return () => k.cleanup();
  },
};

const row = (p, depth, isPending, manage) => html`
  <li class="tree-row depth-${Math.min(depth, 5)} ${p.is_active === false ? 'is-inactive' : ''}">
    <span class="indent"></span><span class="glyph">${icon(p.level === 'site' ? 'pin' : 'folder')}</span>
    <div class="grow"><div class="name">${isPending ? html`<span class="pending-dot" title="Waiting to sync"></span>` : ''}${p.name}</div>
      <div class="meta">${LABEL[p.level] ?? p.level}${p.code ? ` · ${p.code}` : ''}${p.is_active === false ? ' · inactive' : ''}</div></div>
    ${manage ? html`<button class="icon-btn" type="button" data-add="${p.id}" aria-label="Add a place inside ${p.name}" title="Add inside">${icon('plus')}</button>
      <button class="icon-btn" type="button" data-edit="${p.id}" aria-label="Edit ${p.name}">${icon('edit')}</button>
      <button class="icon-btn" type="button" data-del="${p.id}" aria-label="Remove ${p.name}">${icon('trash')}</button>` : ''}
  </li>`;

/** Depth-first, siblings alphabetical. Filtering keeps the ancestors of every match so context is never lost. */
function flatten(places, q) {
  const kids = new Map();
  for (const p of places) { const key = p.parent_id && places.some((x) => x.id === p.parent_id) ? p.parent_id : ''; (kids.get(key) ?? kids.set(key, []).get(key)).push(p); }
  for (const list of kids.values()) list.sort((a, b) => a.name.localeCompare(b.name));
  const needle = q.trim().toLowerCase();
  const matches = (p) => !needle || p.name.toLowerCase().includes(needle) || (p.code ?? '').toLowerCase().includes(needle);
  const keep = (p) => matches(p) || (kids.get(p.id) ?? []).some(keep);
  const out = [];
  const walk = (parent, depth) => { for (const p of kids.get(parent) ?? []) { if (keep(p)) { out.push({ p, depth }); walk(p.id, depth + 1); } } };
  walk('', 0);
  return out;
}
const depthOf = (p, map) => { let d = 0; for (let cur = p; cur?.parent_id && map.get(cur.parent_id) && d < 8; d++) cur = map.get(cur.parent_id); return d; };
const isInside = (p, ancestorId, map) => { if (!ancestorId) return false; for (let cur = p, n = 0; cur && n < 10; n++) { if (cur.parent_id === ancestorId) return true; cur = map.get(cur.parent_id); } return false; };
