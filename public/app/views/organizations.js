import * as api from '../core/api.js';
import { icon } from '../core/icons.js';
import { href, navigate } from '../core/router.js';
import { busy, confirmDialog, field, readForm, searchInput, select, sheet, showErrors, textarea, toast } from '../core/ui.js';
import { $, debounce, html } from '../core/util.js';

const TYPES = [['partner', 'Partner'], ['donor', 'Donor'], ['government', 'Government'], ['school', 'School'], ['hospital', 'Hospital'], ['ngo', 'NGO'], ['community', 'Community group'], ['business', 'Business'], ['other', 'Other']];
const typeLabel = (t) => TYPES.find(([k]) => k === t)?.[1] ?? t;

// Organizations: the foundation's master list of partners, donors and institutions. A program links to the ones it works with;
// what an organization means to one program is stored on that link, not here.
export default {
  async mount(ctx) {
    if (ctx.params[0]) return detail(ctx, ctx.params[0]);
    const create = ctx.can('organizations.create');
    let rows = [];
    let q = '';
    let type = '';
    let failed = null;
    let loading = true;
    const draw = () => { ctx.root.innerHTML = listView({ rows, q, type, failed, loading, create }).toString(); };
    const load = async () => {
      loading = true;
      try { rows = (await api.get(`/organizations?q=${encodeURIComponent(q)}&type=${type}`)).data; failed = null; } catch (e) { failed = api.explain(e); }
      loading = false; draw();
      if (q) { const i = $('#q', ctx.root); i?.focus(); i?.setSelectionRange(q.length, q.length); }
    };
    const search = debounce(load, 300);
    ctx.root.addEventListener('input', (e) => { if (e.target.id === 'q') { q = e.target.value; search(); } });
    ctx.root.addEventListener('change', (e) => { if (e.target.id === 'ty') { type = e.target.value; load(); } });
    ctx.root.addEventListener('click', (e) => {
      if (e.target.closest('[data-retry]')) return load();
      if (e.target.closest('[data-add]')) return openForm(null, async (data) => { toast('Organization added.'); navigate(`organizations/${data.id}`); });
    });
    draw();
    await load();
  },
};

function listView({ rows, q, type, failed, loading, create }) {
  return html`
    <div class="page-head"><div><h1>Organizations</h1><p>Partners, donors and institutions the foundation works with. Programs link to the ones they use.</p></div>
      ${create ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} Add organization</button></div>` : ''}</div>
    ${failed ? html`<div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>` : ''}
    <div class="card flush"><div class="filters"><div class="grow">${searchInput({ value: q, placeholder: 'Search organizations', label: 'Search organizations' })}</div>
      <div class="field"><label class="sr-only" for="ty">Type</label><select class="select" id="ty"><option value="">All types</option>${TYPES.map(([v, t]) => html`<option value="${v}" ${type === v ? 'selected' : ''}>${t}</option>`)}</select></div></div>
      ${loading && !rows.length ? html`<div class="skeleton row"></div><div class="skeleton row"></div>`
        : rows.length ? html`<ul class="list">${rows.map((o) => html`<li><a class="row-link" href="${href('organizations/' + o.id)}"><span class="avatar sq" aria-hidden="true">${icon('folder')}</span>
          <span class="grow"><span class="t">${o.name}</span><span class="d block">${[typeLabel(o.type), o.country, o.programs_count ? `${o.programs_count} program${o.programs_count === 1 ? '' : 's'}` : null].filter(Boolean).join(' · ')}</span></span>
          ${o.status === 'inactive' ? html`<span class="chip grey">Inactive</span>` : ''}${icon('chevron', 'chev')}</a></li>`)}</ul>`
          : html`<div class="empty">${icon('folder')}<b>${q || type ? 'No organization matches' : 'No organizations yet'}</b><span>${q || type ? 'Try a different search or filter.' : create ? 'Add the first partner or donor.' : 'Nothing has been added yet.'}</span>${create && !q && !type ? html`<button class="btn" type="button" data-add>${icon('plus')} Add organization</button>` : ''}</div>`}</div>`;
}

function openForm(org, done) {
  sheet({
    title: org ? 'Edit organization' : 'Add organization',
    body: html`<form class="form" novalidate>
      ${field({ label: 'Name', name: 'name', value: org?.name, required: true })}
      <div class="row-2">${select({ label: 'Type', name: 'type', value: org?.type ?? 'partner', options: TYPES })}${field({ label: 'Country', name: 'country', value: org?.country })}</div>
      <div class="row-2">${field({ label: 'Location', name: 'location', value: org?.location, hint: 'City or area.' })}${field({ label: 'Phone', name: 'phone', type: 'tel', value: org?.phone })}</div>
      <div class="row-2">${field({ label: 'Email', name: 'email', type: 'email', value: org?.email })}${field({ label: 'Website', name: 'website', type: 'url', value: org?.website })}</div>
      ${textarea({ label: 'Address', name: 'address', value: org?.address })}
      ${textarea({ label: 'Notes', name: 'notes', value: org?.notes })}
      ${org ? select({ label: 'Status', name: 'status', value: org.status, options: [['active', 'Active'], ['inactive', 'Inactive — hidden from new links']] }) : ''}
      <div class="btn-row"><button class="btn" type="submit">${org ? 'Save' : 'Add organization'}</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Saving…');
      try {
        const { data } = org ? await api.patch(`/organizations/${org.id}`, readForm(form)) : await api.post('/organizations', readForm(form));
        close(); await done(data);
      } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
    }),
  });
}

async function detail(ctx, id) {
  const edit = ctx.can('organizations.update');
  let o = null;
  const draw = (failed) => { ctx.root.innerHTML = detailView(o, failed, edit).toString(); if (o) ctx.setTitle(o.name); };
  const load = async () => { try { o = (await api.get(`/organizations/${id}`)).data; draw(null); } catch (e) { draw(e.status === 404 ? 'This organization does not exist.' : api.explain(e)); } };

  ctx.root.addEventListener('click', async (e) => {
    if (e.target.closest('[data-retry]')) return load();
    if (e.target.closest('[data-edit]')) return openForm(o, async () => { toast('Saved.'); await load(); });
    if (e.target.closest('[data-add-contact]')) return openContact(null);
    const c = e.target.closest('[data-contact]');
    if (c) return openContact(o.contacts.find((x) => x.id === c.dataset.contact));
    if (e.target.closest('[data-delete]')) {
      if (!(await confirmDialog({ title: `Remove ${o.name}?`, text: 'This removes it from the foundation\'s list. If a program still uses it you will be asked to unlink it first.', confirmLabel: 'Remove', danger: true }))) return;
      try { await api.del(`/organizations/${id}`); toast('Removed.'); navigate('organizations'); } catch (err) { toast(api.explain(err), 'bad'); }
    }
  });

  function openContact(c) {
    sheet({
      title: c ? c.name : 'Add a contact person',
      body: html`<form class="form" novalidate>
        ${field({ label: 'Name', name: 'name', value: c?.name, required: true })}${field({ label: 'Title / role', name: 'title', value: c?.title })}
        <div class="row-2">${field({ label: 'Email', name: 'email', type: 'email', value: c?.email })}${field({ label: 'Phone', name: 'phone', type: 'tel', value: c?.phone })}</div>
        <label class="check"><span>Main contact</span><input type="checkbox" name="is_primary" ${c?.is_primary ? 'checked' : ''}></label>
        ${textarea({ label: 'Notes', name: 'notes', value: c?.notes })}
        <div class="btn-row"><button class="btn" type="submit">${c ? 'Save' : 'Add contact'}</button><button class="btn secondary" type="button" data-close>Cancel</button>${c ? html`<button class="btn danger" type="button" data-remove>${icon('trash')} Remove</button>` : ''}</div></form>`,
      onMount: (el, close) => {
        const form = $('form', el);
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const btn = $('button[type=submit]', form);
          busy(btn, true, 'Saving…');
          try { await (c ? api.patch(`/organizations/${id}/contacts/${c.id}`, readForm(form)) : api.post(`/organizations/${id}/contacts`, readForm(form))); close(); toast('Saved.'); load(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
        });
        $('[data-remove]', el)?.addEventListener('click', async () => {
          try { await api.del(`/organizations/${id}/contacts/${c.id}`); close(); toast('Removed.'); load(); } catch (err) { toast(api.explain(err), 'bad'); }
        });
      },
    });
  }

  ctx.root.innerHTML = html`<div class="skeleton title"></div><div class="card"><div class="skeleton line"></div></div>`.toString();
  await load();
}

function detailView(o, failed, edit) {
  if (!o) return html`<div class="page-head"><div><div class="crumb"><a href="${href('organizations')}">Organizations</a></div><h1>Organization</h1></div></div>
    <div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>`;
  return html`
    <div><div class="crumb"><a href="${href('organizations')}">Organizations</a>${icon('chevron')}<span>${o.name}</span></div>
      <div class="page-head"><div><h1>${o.name}</h1><div class="prop"><span class="chip blue">${typeLabel(o.type)}</span>${o.status === 'inactive' ? html`<span class="chip grey">Inactive</span>` : ''}<span>${[o.location, o.country].filter(Boolean).join(', ')}</span></div></div>
        ${edit ? html`<div class="btn-row"><button class="btn secondary" type="button" data-edit>${icon('edit')} Edit</button></div>` : ''}</div></div>
    <div class="two-col">
      <section><h2>Contact people</h2><div class="card flush">${o.contacts.length ? html`<ul class="list">${o.contacts.map((c) => html`<li><span class="avatar" aria-hidden="true">${icon('user')}</span>
        <div class="grow"><div class="t">${c.name}${c.is_primary ? html` <span class="chip blue">Main</span>` : ''}</div><div class="d">${[c.title, c.email, c.phone].filter(Boolean).join(' · ')}</div></div>
        ${edit ? html`<button class="icon-btn" type="button" data-contact="${c.id}" aria-label="Edit ${c.name}">${icon('edit')}</button>` : ''}</li>`)}</ul>`
        : html`<div class="empty">${icon('users')}<b>No contact people</b><span>Add who to talk to at this organization.</span></div>`}
        ${edit ? html`<div class="card-head"><span></span><button class="btn sm secondary" type="button" data-add-contact>${icon('plus')} Add contact</button></div>` : ''}</div></section>
      <section><h2>Used by</h2><div class="card flush">${o.programs.length ? html`<ul class="list">${o.programs.map((p) => html`<li><a class="row-link" href="${href('programs/' + p.program_id + '/partners')}"><span class="grow"><span class="t">${p.name}</span><span class="d block">${p.relationship.replace('_', ' ')}</span></span>${icon('chevron', 'chev')}</a></li>`)}</ul>`
        : html`<div class="empty">${icon('heart')}<b>Not linked yet</b><span>Link it from a program's Partners tab.</span></div>`}</div></section>
    </div>
    <section><h2>Details</h2><div class="card"><dl class="kv">
      ${[['Email', o.email], ['Phone', o.phone], ['Website', o.website], ['Address', o.address], ['Notes', o.notes]].filter(([, v]) => v).map(([k, v]) => html`<dt>${k}</dt><dd>${v}</dd>`)}
      ${!o.email && !o.phone && !o.website && !o.address && !o.notes ? html`<dt>Details</dt><dd class="muted">None recorded.</dd>` : ''}</dl></div></section>
    ${edit ? html`<div class="btn-row"><button class="btn sm danger" type="button" data-delete>${icon('trash')} Remove organization</button></div>` : ''}`;
}
