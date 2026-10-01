import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { href, navigate } from '../../core/router.js';
import { busy, confirmDialog, field, readForm, searchInput, select, sheet, showErrors, showSecret, textarea, toast } from '../../core/ui.js';
import { $, ago, debounce, fmtBytes, fmtDate, html, plural } from '../../core/util.js';

const STATUS = { active: ['green', 'Active'], inactive: ['grey', 'Inactive'], suspended: ['red', 'Suspended'] };
const chip = (s) => { const [tone, text] = STATUS[s] ?? ['grey', s]; return html`<span class="chip ${tone}">${text}</span>`; };

// Foundations: the platform creates them, switches them on and off, and hands their administrators a way in.
// It never opens a foundation's own records.
export default {
  async mount(ctx) {
    if (ctx.params[0]) return detail(ctx, ctx.params[0]);
    return list(ctx);
  },
};

// ---------------------------------------------------------------- list
async function list(ctx) {
  const manage = ctx.can('platform.foundations.manage');
  let rows = [];
  let meta = { page: 1, last_page: 1, total: 0 };
  let q = '';
  let status = '';
  let page = 1;
  let failed = null;
  let loading = true;

  const draw = () => { ctx.root.innerHTML = listView({ rows, meta, q, status, failed, loading, manage }).toString(); };
  const load = async () => {
    loading = true;
    try {
      const r = await api.get(`/platform/foundations?page=${page}&q=${encodeURIComponent(q)}&status=${status}`);
      rows = r.data; meta = r.meta; failed = null;
    } catch (e) { failed = api.explain(e); }
    loading = false;
    draw();
    if (q) { const i = $('#q', ctx.root); i?.focus(); i?.setSelectionRange(q.length, q.length); }
  };
  const search = debounce(() => { page = 1; load(); }, 300);

  ctx.root.addEventListener('input', (e) => { if (e.target.id === 'q') { q = e.target.value; search(); } });
  ctx.root.addEventListener('change', (e) => { if (e.target.id === 'st') { status = e.target.value; page = 1; load(); } });
  ctx.root.addEventListener('click', (e) => {
    if (e.target.closest('[data-add]')) return openCreate();
    if (e.target.closest('[data-retry]')) return load();
    const go = e.target.closest('[data-page]');
    if (go) { page = Number(go.dataset.page); load(); }
    const row = e.target.closest('tr[data-edit]');
    if (row) navigate(`foundations/${row.dataset.edit}`);
  });
  draw();
  await load();

  function openCreate() {
    sheet({
      title: 'New foundation',
      body: html`<form class="form" novalidate>
        <p class="muted">This creates the foundation and its first administrator. The administrator gets a temporary password and sets their own at first sign-in.</p>
        ${field({ label: 'Foundation name', name: 'name', required: true })}
        <div class="row-2">${field({ label: 'Short name', name: 'short_name', hint: 'Shown in the app header.' })}${field({ label: 'Country', name: 'country' })}</div>
        <div class="row-2">${field({ label: 'Email', name: 'email', type: 'email' })}${field({ label: 'Phone', name: 'phone', type: 'tel' })}</div>
        <hr><h4>First administrator</h4>
        ${field({ label: 'Full name', name: 'admin_name', required: true, autocomplete: 'off' })}
        ${field({ label: 'Email', name: 'admin_email', type: 'email', required: true, hint: 'They sign in with this.' })}
        <div class="btn-row"><button class="btn" type="submit">Create foundation</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
      onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.currentTarget;
        const v = readForm(form);
        const body = { ...v, admin: { name: v.admin_name, email: v.admin_email } };
        delete body.admin_name; delete body.admin_email;
        const btn = $('button[type=submit]', form);
        busy(btn, true, 'Creating…');
        try {
          const { data } = await api.post('/platform/foundations', body);
          close();
          showSecret({ title: 'Foundation created', who: `${data.administrator.name} (${data.administrator.email})`, password: data.administrator.temporary_password });
          navigate(`foundations/${data.id}`);
        } catch (err) {
          const errors = {};
          for (const [k, msgs] of Object.entries(err.errors ?? {})) errors[k.replace('admin.', 'admin_')] = msgs;
          if (!showErrors(form, errors)) toast(api.explain(err), 'bad');
        } finally { busy(btn, false); }
      }),
    });
  }
}

function listView({ rows, meta, q, status, failed, loading, manage }) {
  return html`
    <div class="page-head"><div><h1>Foundations</h1><p>Every foundation on the platform. Each one is completely separate from the others.</p></div>
      ${manage ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} New foundation</button></div>` : ''}</div>
    ${failed ? html`<div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>` : ''}
    <div class="card flush"><div class="filters">
      <div class="grow">${searchInput({ value: q, placeholder: 'Search foundations', label: 'Search foundations' })}</div>
      <div class="field"><label class="sr-only" for="st">Status</label><select class="select" id="st">${[['', 'All'], ['active', 'Active'], ['inactive', 'Inactive'], ['suspended', 'Suspended']].map(([v, t]) => html`<option value="${v}" ${status === v ? 'selected' : ''}>${t}</option>`)}</select></div></div>
      ${loading && !rows.length ? html`<div class="skeleton row"></div><div class="skeleton row"></div><div class="skeleton row"></div>`
        : rows.length ? html`<table class="table"><thead><tr><th scope="col">Foundation</th><th scope="col">Status</th><th scope="col">People</th><th scope="col">Programs</th></tr></thead>
          <tbody>${rows.map((f) => html`<tr data-edit="${f.id}"><td class="c-name"><div class="cell"><span class="avatar sq" aria-hidden="true">${icon('building')}</span><div><div class="t">${f.name}</div><div class="d muted">${f.country ?? f.slug}</div></div></div></td>
            <td class="c-status">${chip(f.status)}</td><td class="c-role">${f.users_count}</td><td class="c-role">${f.programs_count}</td></tr>`)}</tbody></table>
          ${meta.last_page > 1 ? html`<div class="btn-row center"><button class="btn secondary sm" type="button" data-page="${meta.page - 1}" ${meta.page <= 1 ? 'disabled' : ''}>Previous</button><span class="muted">Page ${meta.page} of ${meta.last_page}</span><button class="btn secondary sm" type="button" data-page="${meta.page + 1}" ${meta.page >= meta.last_page ? 'disabled' : ''}>Next</button></div>` : ''}`
          : html`<div class="empty">${icon('building')}<b>${q || status ? 'No foundation matches' : 'No foundations yet'}</b><span>${q || status ? 'Try a different search or filter.' : 'Create the first foundation to get started.'}</span>${manage && !q && !status ? html`<button class="btn" type="button" data-add>${icon('plus')} New foundation</button>` : ''}</div>`}
    </div>`;
}

// ---------------------------------------------------------------- detail
async function detail(ctx, id) {
  const manage = ctx.can('platform.foundations.manage');
  let f = null;
  const draw = (failed) => { ctx.root.innerHTML = detailView(f, failed, manage).toString(); if (f) ctx.setTitle(f.name); };
  const load = async () => {
    try { f = (await api.get(`/platform/foundations/${id}`)).data; draw(null); } catch (e) { draw(e.status === 404 ? 'This foundation does not exist.' : api.explain(e)); }
  };

  ctx.root.addEventListener('click', async (e) => {
    if (e.target.closest('[data-retry]')) return load();
    if (e.target.closest('[data-edit]')) return openEdit();
    if (e.target.closest('[data-add-admin]')) return openAddAdmin();
    const status = e.target.closest('[data-status]');
    if (status) return changeStatus(status.dataset.status);
    const reset = e.target.closest('[data-reset]');
    if (reset) {
      const a = f.administrators.find((x) => x.id === reset.dataset.reset);
      if (!(await confirmDialog({ title: `Reset ${a.name}'s password?`, text: 'They are signed out everywhere and must use the new temporary password.', confirmLabel: 'Reset password' }))) return;
      try { const { data } = await api.post(`/platform/foundations/${id}/administrators/${a.id}/reset-password`); showSecret({ title: 'Password reset', who: a.name, password: data.temporary_password }); } catch (err) { toast(api.explain(err), 'bad'); }
    }
  });

  async function changeStatus(to) {
    const copy = {
      active: ['Activate this foundation?', 'Its people can sign in again.', 'Activate'],
      inactive: ['Deactivate this foundation?', 'Its people are signed out and cannot sign in until it is activated again. Nothing is deleted.', 'Deactivate'],
      suspended: ['Suspend this foundation?', 'Its people are signed out and cannot sign in, and its public registration links close. Use this for a problem that needs investigating. Nothing is deleted.', 'Suspend'],
    }[to];
    let reason = null;
    if (to !== 'active') {
      reason = await new Promise((resolve) => {
        let done = false;
        sheet({
          title: copy[0],
          body: html`<form class="form" novalidate><p class="muted">${copy[1]}</p>${textarea({ label: 'Reason (kept in the platform activity)', name: 'reason' })}
            <div class="btn-row"><button class="btn danger" type="submit">${copy[2]}</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
          onMount: (el, close) => $('form', el).addEventListener('submit', (ev) => { ev.preventDefault(); done = true; resolve(readForm(ev.currentTarget).reason ?? ''); close(); }),
          onClose: () => { if (!done) resolve(undefined); },
        });
      });
      if (reason === undefined) return;
    } else if (!(await confirmDialog({ title: copy[0], text: copy[1], confirmLabel: copy[2] }))) return;
    try { await api.post(`/platform/foundations/${id}/status`, { status: to, reason: reason || null }); toast('Status changed.'); load(); } catch (err) { toast(api.explain(err), 'bad'); }
  }

  function openEdit() {
    sheet({
      title: 'Foundation details',
      body: html`<form class="form" novalidate>
        ${field({ label: 'Name', name: 'name', value: f.name, required: true })}
        <div class="row-2">${field({ label: 'Legal name', name: 'legal_name', value: f.legal_name })}${field({ label: 'Short name', name: 'short_name', value: f.short_name })}</div>
        <div class="row-2">${field({ label: 'Country', name: 'country', value: f.country })}${field({ label: 'Registration number', name: 'registration_number', value: f.registration_number })}</div>
        <div class="row-2">${field({ label: 'Email', name: 'email', type: 'email', value: f.email })}${field({ label: 'Phone', name: 'phone', type: 'tel', value: f.phone })}</div>
        ${field({ label: 'Website', name: 'website', type: 'url', value: f.website })}
        ${textarea({ label: 'Address', name: 'address', value: f.address })}
        ${textarea({ label: 'Description', name: 'description', value: f.description })}
        <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
      onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.currentTarget;
        const btn = $('button[type=submit]', form);
        busy(btn, true, 'Saving…');
        try { await api.patch(`/platform/foundations/${id}`, readForm(form)); close(); toast('Saved.'); load(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
      }),
    });
  }

  function openAddAdmin() {
    sheet({
      title: 'Add an administrator',
      body: html`<form class="form" novalidate>
        <p class="muted">Use this when the foundation's administrator left or is locked out. The new person gets a temporary password.</p>
        ${field({ label: 'Full name', name: 'name', required: true })}${field({ label: 'Email', name: 'email', type: 'email', required: true })}${field({ label: 'Phone', name: 'phone', type: 'tel' })}
        <div class="btn-row"><button class="btn" type="submit">Add administrator</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
      onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.currentTarget;
        const btn = $('button[type=submit]', form);
        busy(btn, true, 'Adding…');
        try { const { data } = await api.post(`/platform/foundations/${id}/administrators`, readForm(form)); close(); showSecret({ title: 'Administrator added', who: data.name, password: data.temporary_password }); load(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
      }),
    });
  }

  ctx.root.innerHTML = html`<div class="skeleton title"></div><div class="card"><div class="skeleton line"></div><div class="skeleton line short"></div></div>`.toString();
  await load();
}

function detailView(f, failed, manage) {
  if (!f) return html`<div class="page-head"><div><div class="crumb"><a href="${href('foundations')}">Foundations</a></div><h1>Foundation</h1></div></div>
    <div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>`;
  const u = f.usage;
  return html`
    <div><div class="crumb"><a href="${href('foundations')}">Foundations</a>${icon('chevron')}<span>${f.name}</span></div>
      <div class="page-head"><div><h1>${f.name}</h1><div class="prop">${chip(f.status)}<span>${f.country ?? ''}</span><span>Created ${fmtDate(f.created_at)}</span></div></div>
        ${manage ? html`<div class="btn-row"><button class="btn secondary" type="button" data-edit>${icon('edit')} Edit details</button></div>` : ''}</div></div>
    ${f.status !== 'active' ? html`<div class="banner ${f.status === 'suspended' ? 'bad' : 'warn'}">${icon('lock')}<div class="grow"><b>${f.status === 'suspended' ? 'Suspended' : 'Inactive'}.</b> Its people cannot sign in.${f.status_reason ? ` Reason: ${f.status_reason}.` : ''}</div></div>` : ''}

    <div class="two-col">
      <section><h2>Administrators</h2><div class="card flush">${f.administrators.length ? html`<ul class="list">${f.administrators.map((a) => html`<li><span class="avatar" aria-hidden="true">${icon('user')}</span>
        <div class="grow"><div class="t">${a.name}</div><div class="d">${a.email} · ${a.last_login_at ? `last signed in ${ago(a.last_login_at)}` : 'never signed in'}</div></div>
        ${a.status === 'disabled' ? html`<span class="chip red">Disabled</span>` : ''}${manage ? html`<button class="btn sm secondary" type="button" data-reset="${a.id}">${icon('key')} Reset password</button>` : ''}</li>`)}</ul>`
        : html`<div class="empty">${icon('users')}<b>No administrator</b><span>Add one so the foundation can be set up.</span></div>`}
        ${manage ? html`<div class="card-head"><span></span><button class="btn sm secondary" type="button" data-add-admin>${icon('plus')} Add administrator</button></div>` : ''}</div></section>

      <section><h2>Usage</h2><div class="card"><dl class="kv">
        <dt>People</dt><dd>${u.users}</dd><dt>Programs</dt><dd>${u.programs}${u.active_programs !== u.programs ? ` (${u.active_programs} active)` : ''}</dd>
        <dt>Organizations</dt><dd>${u.organizations}</dd><dt>Devices</dt><dd>${u.devices}</dd><dt>Stored documents</dt><dd>${fmtBytes(u.storage_bytes)}</dd></dl>
        <p class="hint">Only totals are shown here. The platform does not open a foundation's records.</p></div></section>
    </div>

    <section><h2>Profile</h2><div class="card"><dl class="kv">
      ${[['Legal name', f.legal_name], ['Short name', f.short_name], ['Email', f.email], ['Phone', f.phone], ['Website', f.website], ['Address', f.address], ['Registration number', f.registration_number], ['Description', f.description]].filter(([, v]) => v).map(([k, v]) => html`<dt>${k}</dt><dd>${v}</dd>`)}
      <dt>Link name</dt><dd><code>${f.slug}</code></dd></dl></div></section>

    ${manage ? html`<section><h2>Status</h2><div class="card flush"><ul class="list">
      ${f.status !== 'active' ? html`<li><div class="grow"><div class="t">Activate</div><div class="d">Let its people sign in.</div></div><button class="btn sm" type="button" data-status="active">Activate</button></li>` : ''}
      ${f.status !== 'inactive' ? html`<li><div class="grow"><div class="t">Deactivate</div><div class="d">Pause the foundation. Nothing is deleted.</div></div><button class="btn sm secondary" type="button" data-status="inactive">Deactivate</button></li>` : ''}
      ${f.status !== 'suspended' ? html`<li><div class="grow"><div class="t">Suspend</div><div class="d">Block access while a problem is looked into.</div></div><button class="btn sm danger" type="button" data-status="suspended">Suspend</button></li>` : ''}</ul></div></section>` : ''}`;
}
