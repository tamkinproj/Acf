import * as api from '../core/api.js';
import { db } from '../core/db.js';
import { icon } from '../core/icons.js';
import { busy, toast } from '../core/ui.js';
import { html, plural } from '../core/util.js';
import { kick } from '../sync/engine.js';
import { kit } from './kit.js';

// Roles: what each kind of person may do. Reading is local; saving needs a connection because a permission change
// must take effect everywhere at once, and the server checks it.
export default {
  async mount(ctx) {
    const k = kit(ctx);
    const manage = ctx.can('roles.manage');
    let roles = [];
    let catalog = [];
    let counts = new Map();
    let openId = null;
    let draft = null;
    let loadError = '';

    const draw = () => k.render(view(roles, catalog, counts, manage, openId, draft, loadError));
    k.live(async () => ({ roles: (await db.roles.filter((r) => !r.deleted_at).toArray()).sort((a, b) => a.name.localeCompare(b.name)), users: await db.users.filter((u) => !u.deleted_at).toArray() }), (d) => {
      roles = d.roles;
      counts = new Map();
      d.users.forEach((u) => counts.set(u.role_id, (counts.get(u.role_id) || 0) + 1));
      draw();
    });
    api.get('/permissions').then(({ data }) => { catalog = data; loadError = ''; draw(); }).catch((e) => { loadError = e.network ? 'The list of permissions needs a connection. You can still see what each role holds.' : api.explain(e); draw(); });

    k.on('click', '[data-open]', (e, t) => { openId = openId === t.dataset.open ? null : t.dataset.open; draft = openId ? new Set(roles.find((r) => r.id === openId).permissions || []) : null; draw(); });
    k.on('change', '[data-perm]', (e, t) => { t.checked ? draft.add(t.dataset.perm) : draft.delete(t.dataset.perm); draw(); const el = ctx.root.querySelector(`[data-perm="${CSS.escape(t.dataset.perm)}"]`); el?.focus(); });
    k.on('click', '[data-group]', (e, t) => {
      const keys = catalog.filter((p) => p.group === t.dataset.group).map((p) => p.key);
      const all = keys.every((x) => draft.has(x));
      keys.forEach((x) => (all ? draft.delete(x) : draft.add(x)));
      draw();
    });
    k.on('click', '[data-save]', async (e, t) => {
      busy(t, true, 'Saving…');
      try { await api.put(`/roles/${openId}/permissions`, { permissions: [...draft] }); kick(0); toast('Permissions saved'); openId = null; draft = null; } catch (err) { toast(api.explain(err), 'bad'); }
      busy(t, false);
      draw();
    });
    return () => k.cleanup();
  },
};

function view(roles, catalog, counts, manage, openId, draft, loadError) {
  const groups = {};
  catalog.forEach((p) => (groups[p.group] ||= []).push(p));
  return html`
    <div class="page-head"><div><h2>Roles</h2><p>Each person has one role. A role is a set of things they are allowed to do.</p></div></div>
    ${loadError ? html`<div class="banner info">${icon('info')}<div class="grow">${loadError}</div></div>` : ''}
    <div class="stack">${roles.map((r) => {
      const open = r.id === openId;
      const locked = r.key === 'super_admin';
      return html`<div class="card ${open ? 'open' : ''}">
        <button class="row-btn" type="button" data-open="${r.id}" aria-expanded="${open}">
          <span class="grow"><b>${r.name}</b>${r.is_system ? html` <span class="chip grey">Built in</span>` : ''}<span class="d">${r.description || ''}</span></span>
          <span class="muted">${plural(counts.get(r.id) || 0, 'person', 'people')} · ${locked ? 'everything' : plural((r.permissions || []).length, 'permission')}</span>${icon('chevron')}</button>
        ${open ? html`<div class="perm-grid">
          ${locked ? html`<div class="banner info">${icon('shield')}<div class="grow">Super Admin always holds every permission and cannot be changed.</div></div>` : ''}
          ${catalog.length ? Object.entries(groups).map(([g, list]) => html`<fieldset ${locked || !manage ? 'disabled' : ''}><legend>${g}${manage && !locked ? html` <button class="link" type="button" data-group="${g}">toggle all</button>` : ''}</legend>
            ${list.map((p) => html`<label class="check"><input type="checkbox" data-perm="${p.key}" ${locked || draft?.has(p.key) ? 'checked' : ''}> <span>${p.label}</span></label>`)}</fieldset>`)
            : html`<p class="muted">${(r.permissions || []).join(', ') || 'No permissions'}</p>`}
          ${manage && !locked && catalog.length ? html`<div class="btn-row"><button class="btn" type="button" data-save>Save permissions</button><button class="btn ghost" type="button" data-open="${r.id}">Cancel</button></div>` : ''}</div>` : ''}</div>`;
    })}</div>`;
}
