import * as api from '../core/api.js';
import { db } from '../core/db.js';
import { icon } from '../core/icons.js';
import { busy, confirmDialog, field, readForm, select, sheet, showErrors, textarea, toast } from '../core/ui.js';
import { $, html, plural } from '../core/util.js';
import { kick } from '../sync/engine.js';
import { kit } from './kit.js';

// Roles: what each kind of person may do. Reading is local; saving needs a connection because a permission change
// must take effect everywhere at once, and the server checks it.
//
// Two kinds: foundation roles (what someone may do across the foundation) and program roles (what someone may do inside one
// program, such as the Aytam Mushrif). A foundation role may also hold program permissions, which then apply to every program.
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
      const role = roles.find((r) => r.id === openId);
      const keys = catalog.filter((p) => p.group === t.dataset.group && allowedFor(role, p)).map((p) => p.key);
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
    k.on('click', '[data-new]', () => openNew());
    k.on('click', '[data-delete]', async (e, t) => {
      const role = roles.find((r) => r.id === t.dataset.delete);
      if (!(await confirmDialog({ title: `Delete the role "${role.name}"?`, text: 'This only works when no one holds it.', confirmLabel: 'Delete role', danger: true }))) return;
      try { await api.del(`/roles/${role.id}`); kick(0); toast('Role deleted'); openId = null; draft = null; draw(); } catch (err) { toast(api.explain(err), 'bad'); }
    });

    function openNew() {
      sheet({
        title: 'New role',
        body: html`<form class="form" novalidate>
          ${field({ label: 'Name', name: 'name', required: true, hint: 'For example "Case reviewer".' })}
          ${textarea({ label: 'What is it for?', name: 'description' })}
          ${select({ label: 'Kind of role', name: 'scope', value: 'foundation', options: [['foundation', 'Foundation role — assigned to a person'], ['program', 'Program role — assigned per program']], hint: 'You choose what it may do after it is created.' })}
          <div class="btn-row"><button class="btn" type="submit">Create role</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
        onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
          e.preventDefault();
          const form = e.currentTarget;
          const btn = $('button[type=submit]', form);
          busy(btn, true, 'Creating…');
          try {
            const { data } = await api.post('/roles', { ...readForm(form), permissions: [] });
            close(); kick(0); toast('Role created. Choose what it may do.');
            openId = data.id; draft = new Set();
          } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
        }),
      });
    }
    return () => k.cleanup();
  },
};

/** A program role can hold program permissions only; a foundation role can hold both. */
const allowedFor = (role, p) => role?.scope !== 'program' || p.scope === 'program';

function row(r, { catalog, groups, counts, manage, openId, draft }) {
  const open = r.id === openId;
  const locked = r.key === 'foundation_admin';
  const people = r.scope === 'program' ? 'assigned per program' : plural(counts.get(r.id) || 0, 'person', 'people');
  return html`<div class="acc ${open ? 'open' : ''}">
    <button class="row-btn" type="button" data-open="${r.id}" aria-expanded="${open}">
      <span class="grow"><b>${r.name}</b>${r.is_system ? html` <span class="chip grey">Built in</span>` : ''}<span class="d">${r.description || ''}</span></span>
      <span class="muted">${people} · ${locked ? 'everything' : plural((r.permissions || []).length, 'permission')}</span>${icon('chevron')}</button>
    ${open ? html`<div class="perm-grid">
      ${locked ? html`<div class="banner info">${icon('shield')}<div class="grow">Foundation Admin always holds every permission and cannot be changed, so the foundation can never be locked out.</div></div>` : ''}
      ${catalog.length ? Object.entries(groups).map(([g, list]) => {
        const mine = list.filter((p) => allowedFor(r, p));
        return mine.length ? html`<fieldset ${locked || !manage ? 'disabled' : ''}><legend>${g}${manage && !locked ? html` · <button class="link" type="button" data-group="${g}">toggle all</button>` : ''}</legend>
          ${mine.map((p) => html`<label class="check"><input type="checkbox" data-perm="${p.key}" ${locked || draft?.has(p.key) ? 'checked' : ''}> <span>${p.label}</span></label>`)}</fieldset>` : '';
      }) : html`<p class="muted">${(r.permissions || []).join(', ') || 'No permissions'}</p>`}
      ${manage && !locked && catalog.length ? html`<div class="btn-row"><button class="btn" type="button" data-save>Save permissions</button><button class="btn secondary" type="button" data-open="${r.id}">Cancel</button>
        ${!r.is_system ? html`<button class="btn sm danger" type="button" data-delete="${r.id}">${icon('trash')} Delete role</button>` : ''}</div>` : ''}</div>` : ''}</div>`;
}

function view(roles, catalog, counts, manage, openId, draft, loadError) {
  const groups = {};
  catalog.forEach((p) => (groups[p.group] ||= []).push(p));
  const env = { catalog, groups, counts, manage, openId, draft };
  const people = roles.filter((r) => r.scope !== 'program');
  const programRoles = roles.filter((r) => r.scope === 'program');
  return html`
    <div class="page-head"><div><h1>Roles</h1><p>A role is a set of things a person is allowed to do.</p></div>
      ${manage ? html`<div class="btn-row"><button class="btn" type="button" data-new>${icon('plus')} New role</button></div>` : ''}</div>
    ${loadError ? html`<div class="banner info">${icon('info')}<div class="grow">${loadError}</div></div>` : ''}
    <section><h2>Roles for people</h2><p class="hint">Each person has one. It applies across the foundation.</p>
      <div class="card flush">${people.length ? people.map((r) => row(r, env)) : html`<div class="empty">${icon('shield')}<b>No roles yet</b></div>`}</div></section>
    <section><h2>Program roles</h2><p class="hint">Given to a person inside one program (on that program's Team tab), such as the Aytam Mushrif.</p>
      <div class="card flush">${programRoles.length ? programRoles.map((r) => row(r, env)) : html`<div class="empty">${icon('shield')}<b>No program roles yet</b></div>`}</div></section>`;
}
