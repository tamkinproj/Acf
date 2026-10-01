import * as api from '../core/api.js';
import { db } from '../core/db.js';
import { icon } from '../core/icons.js';
import { busy, confirmDialog, copyText, field, readForm, searchInput, select, sheet, showErrors, toast } from '../core/ui.js';
import { $, esc, html, initials, raw } from '../core/util.js';
import { kick } from '../sync/engine.js';
import { removeRecord, UNSYNCED, updateRecord } from '../sync/outbox.js';
import { kit } from './kit.js';

// People. Editing a profile, role or status works offline like any other record. Creating an account or resetting a
// password needs the server (a password has to be generated and hashed there), so those two actions need a connection.
export default {
  async mount(ctx) {
    const k = kit(ctx);
    const manage = ctx.can('users.manage');
    const me = ctx.session.get().user.id;
    let users = [];
    let roles = [];
    let pending = new Set();
    let q = '';
    let status = 'all';
    let sortKey = 'name';
    let sortDir = 1;
    let limit = 25;
    const draw = () => {
      const term = q.toLowerCase();
      const roleName = new Map(roles.map((r) => [r.id, r.name]));
      const val = (u) => (sortKey === 'role' ? roleName.get(u.role_id) ?? '' : sortKey === 'status' ? u.status : u.name).toLowerCase();
      const shown = users.filter((u) => (status === 'all' || u.status === status) && (!term || `${u.name} ${u.email}`.toLowerCase().includes(term)))
        .sort((a, b) => val(a).localeCompare(val(b)) * sortDir || a.name.localeCompare(b.name));
      k.render(view(shown.slice(0, limit), shown.length, roleName, pending, manage, me, q, status, users.length, sortKey, sortDir));
      const input = ctx.root.querySelector('#q');
      if (q && input) { input.focus(); input.setSelectionRange(q.length, q.length); }
    };
    k.live(() => db.users.filter((u) => !u.deleted_at).toArray(), (r) => { users = r.sort((a, b) => a.name.localeCompare(b.name)); draw(); });
    k.live(() => db.roles.filter((r) => !r.deleted_at).toArray(), (r) => { roles = r; draw(); });
    k.live(() => db.outbox.where('entity').equals('users').filter((e) => UNSYNCED.includes(e.status)).toArray(), (r) => { pending = new Set(r.map((e) => e.entity_id)); draw(); });

    k.on('input', '#q', (e, t) => { q = t.value; limit = 25; draw(); });
    k.on('change', '#st', (e, t) => { status = t.value; limit = 25; draw(); });
    k.on('click', '[data-sort]', (e, t) => { const key = t.dataset.sort; sortDir = sortKey === key ? -sortDir : 1; sortKey = key; draw(); });
    k.on('click', '[data-more]', () => { limit += 25; draw(); });
    k.on('click', '[data-add]', () => openCreate(roles));
    k.on('click', 'tr[data-edit]', (e, t) => openEdit(users.find((u) => u.id === t.dataset.edit), roles, me));
    return () => k.cleanup();
  },
};

function tempPasswordSheet(title, who, password) {
  sheet({
    title,
    body: html`<div class="stack"><p>Give this temporary password to <b>${who}</b>. They will be asked to choose their own at first sign-in.</p>
      <div class="secret"><code>${password}</code><button class="btn sm ghost" type="button" data-copy>${icon('copy')} Copy</button></div>
      <div class="banner warn">${icon('alert')}<div class="grow">It is shown only once. If it is lost, reset the password again.</div></div>
      <div class="btn-row"><button class="btn" type="button" data-close>Done</button></div></div>`,
    onMount: (el) => $('[data-copy]', el).addEventListener('click', () => copyText(password)),
  });
}

function openCreate(roles) {
  sheet({
    title: 'Add a person',
    body: html`<form class="form" novalidate>
      <div class="banner info">${icon('cloud')}<div class="grow">Creating an account needs a connection, because the password is made on the server.</div></div>
      ${field({ label: 'Full name', name: 'name', required: true, autocomplete: 'name' })}
      ${field({ label: 'Email', name: 'email', type: 'email', required: true, autocomplete: 'off', hint: 'They sign in with this.' })}
      ${field({ label: 'Phone', name: 'phone', type: 'tel' })}
      ${select({ label: 'Role', name: 'role_id', options: [['', 'Choose a role…'], ...roles.map((r) => [r.id, r.name])] })}
      <div class="btn-row"><button class="btn" type="submit">Create account</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const v = readForm(form);
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Creating…');
      try {
        const { data } = await api.post('/users', v);
        close();
        kick(0);
        tempPasswordSheet('Account created', data.name, data.temporary_password);
      } catch (err) {
        if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad');
      } finally { busy(btn, false); }
    }),
  });
}

function openEdit(u, roles, me) {
  if (!u) return;
  const self = u.id === me;
  sheet({
    title: u.name,
    body: html`<form class="form" novalidate>
      ${field({ label: 'Full name', name: 'name', value: u.name, required: true })}
      ${field({ label: 'Email', name: 'email', type: 'email', value: u.email, required: true })}
      ${field({ label: 'Phone', name: 'phone', type: 'tel', value: u.phone })}
      ${select({ label: 'Role', name: 'role_id', value: u.role_id, options: roles.map((r) => [r.id, r.name]), disabled: self, hint: self ? 'You cannot change your own role.' : '' })}
      ${select({ label: 'Status', name: 'status', value: u.status, options: [['active', 'Active'], ['disabled', 'Disabled — cannot sign in']], disabled: self, hint: self ? 'You cannot disable yourself.' : '' })}
      <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn secondary" type="button" data-close>Cancel</button></div>
      <hr><div class="btn-row">
        <button class="btn sm ghost" type="button" data-reset>${icon('key')} Reset password</button>
        ${self ? '' : html`<button class="btn sm danger" type="button" data-remove>${icon('trash')} Remove person</button>`}</div></form>`,
    onMount: (el, close) => {
      const form = $('form', el);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const v = readForm(form);
        if (self) { delete v.role_id; delete v.status; }
        if (!v.name || !v.email) { showErrors(form, { name: !v.name && 'Enter a name.', email: !v.email && 'Enter an email.' }); return; }
        const changed = Object.fromEntries(Object.entries(v).filter(([key, val]) => (u[key] ?? null) !== val));
        if (Object.keys(changed).length) await updateRecord('users', u.id, changed);
        close();
        toast(Object.keys(changed).length ? 'Saved on this device. It will sync when connected.' : 'Nothing changed');
      });
      $('[data-reset]', el).addEventListener('click', async () => {
        if (!(await confirmDialog({ title: 'Reset password?', text: `${u.name} will be signed out everywhere and must use a new temporary password.`, confirmLabel: 'Reset password' }))) return;
        try { const { data } = await api.post(`/users/${u.id}/reset-password`); close(); tempPasswordSheet('Password reset', u.name, data.temporary_password); } catch (err) { toast(api.explain(err), 'bad'); }
      });
      $('[data-remove]', el)?.addEventListener('click', async () => {
        if (!(await confirmDialog({ title: `Remove ${u.name}?`, text: 'They can no longer sign in. Their past activity stays in the log.', confirmLabel: 'Remove', danger: true }))) return;
        await removeRecord('users', u.id);
        close();
        toast('Removed. It will sync when connected.');
      });
    },
  });
}

function view(users, total, roleName, pending, manage, me, q, status, everyone, sortKey, sortDir) {
  const th = (key, label) => html`<th scope="col" aria-sort="${sortKey === key ? (sortDir === 1 ? 'ascending' : 'descending') : 'none'}"><button class="th" type="button" data-sort="${key}" aria-pressed="${sortKey === key}">${label}${sortKey === key ? icon(sortDir === 1 ? 'arrowUp' : 'arrowDown') : ''}</button></th>`;
  return html`
    <div class="page-head"><div><h1>People</h1><p>Everyone who can sign in to this foundation's system.</p></div>
      ${manage ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} Add person</button></div>` : ''}</div>
    <div class="card flush"><div class="filters">
      <div class="grow">${searchInput({ value: q, placeholder: 'Search people', label: 'Search people' })}</div>
      <div class="field"><label class="sr-only" for="st">Status</label><select class="select" id="st">${[['all', 'Everyone'], ['active', 'Active'], ['disabled', 'Disabled']].map(([v, t]) => html`<option value="${v}" ${status === v ? 'selected' : ''}>${t}</option>`)}</select></div></div>
      ${users.length ? html`<table class="table"><thead><tr>${th('name', 'Name')}${th('role', 'Role')}${th('status', 'Status')}<th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>${users.map((u) => html`<tr ${manage ? raw(`data-edit="${esc(u.id)}"`) : ''}>
          <td class="c-name"><div class="cell"><span class="avatar" aria-hidden="true">${initials(u.name)}</span><div><div class="t">${u.name}${u.id === me ? html` <span class="chip blue">You</span>` : ''}${pending.has(u.id) ? html` <span class="pending-dot" title="Waiting to sync" aria-label="Waiting to sync"></span>` : ''}</div><div class="d muted">${u.email}</div></div></div></td>
          <td class="c-role"><span class="chip">${roleName.get(u.role_id) ?? 'No role'}</span></td>
          <td class="c-status">${u.status === 'disabled' ? html`<span class="chip red">Disabled</span>` : html`<span class="chip green">Active</span>`}</td>
          <td class="c-act">${manage ? html`<button class="icon-btn" type="button" aria-label="Edit ${u.name}">${icon('edit')}</button>` : ''}</td></tr>`)}</tbody></table>
        ${total > users.length ? html`<div class="btn-row center"><button class="btn secondary" type="button" data-more>Show more (${total - users.length} left)</button></div>` : ''}`
        : html`<div class="empty">${icon('users')}<b>${everyone ? 'No one matches' : 'No people yet'}</b><span>${everyone ? 'Try a different search or filter.' : 'People appear here after the first sync.'}</span></div>`}</div>`;
}
