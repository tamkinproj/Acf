import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { href } from '../../core/router.js';
import { busy, confirmDialog, field, readForm, searchInput, sheet, toast } from '../../core/ui.js';
import { $, debounce, html, initials, plural } from '../../core/util.js';
import { aytamChip, area, errorView, loadingView, submitForm } from './children.js';

// Guardians: the adult who looks after one or more children. A guardian can be set on a family (for all its children)
// or on a single child.
//   #/programs/<id>/guardians              list
//   #/programs/<id>/guardians/<guardianId> one guardian, with the families and children they look after
export default {
  async mount(tab) {
    const [id] = tab.params;
    return id ? mountDetail(tab, id) : mountList(tab);
  },
};

const KEYS = ['full_name', 'relationship', 'phone', 'email', 'address', 'notes'];

function guardianSheet(tab, guardian, done) {
  const pid = tab.program.id;
  const g = guardian ?? {};
  sheet({
    title: guardian ? 'Edit guardian' : 'Add a guardian',
    body: html`<form class="form" novalidate>
      ${field({ label: 'Full name', name: 'full_name', value: g.full_name, required: true, autocomplete: 'name' })}
      ${field({ label: 'Relationship to the child', name: 'relationship', value: g.relationship, hint: 'For example uncle, grandmother or neighbour.' })}
      <div class="row-2">${field({ label: 'Phone', name: 'phone', type: 'tel', value: g.phone, autocomplete: 'tel' })}${field({ label: 'Email', name: 'email', type: 'email', value: g.email, autocomplete: 'email' })}</div>
      ${area({ label: 'Address', name: 'address', value: g.address })}
      ${area({ label: 'Notes', name: 'notes', value: g.notes })}
      <div class="btn-row"><button class="btn" type="submit">${guardian ? 'Save' : 'Add guardian'}</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => {
      const form = $('form', el);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const v = readForm(form);
        const body = guardian
          ? Object.fromEntries(KEYS.filter((n) => (v[n] ?? null) !== (g[n] ?? null)).map((n) => [n, v[n] ?? null]))
          : Object.fromEntries(KEYS.filter((n) => v[n] !== null && v[n] !== undefined).map((n) => [n, v[n]]));
        if (guardian && !Object.keys(body).length) { close(); toast('Nothing changed.'); return; }
        await submitForm(form, 'Saving…', async () => {
          const { data } = guardian ? await api.patch(`/programs/${pid}/guardians/${guardian.id}`, body) : await api.post(`/programs/${pid}/guardians`, body);
          close();
          toast(guardian ? 'Saved.' : `${data.full_name} was added.`);
          done(data);
        });
      });
    },
  });
}

// ---- list ---------------------------------------------------------------------------------------------------------
function mountList(tab) {
  const pid = tab.program.id;
  const canWrite = tab.pcan('aytam.update');
  let q = (history.state?.guardList?.pid === pid ? history.state.guardList.q : '') ?? '';
  let seq = 0;
  let alive = true;
  tab.setTitle('Guardians');
  tab.root.innerHTML = html`<div class="stack-lg">
    <div class="page-head"><div><h2 class="section-title">Guardians</h2><p class="muted">The adults who look after the children, with how to reach them.</p></div>
      ${canWrite ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} Add a guardian</button></div>` : ''}</div>
    <div class="card flush"><div class="filters" role="search"><div class="grow">${searchInput({ id: 'g-q', value: q, placeholder: 'Search by name or phone', label: 'Search guardians' })}</div></div>
      <div data-results aria-live="polite"></div></div></div>`;
  const results = $('[data-results]', tab.root);

  const load = async () => {
    const mine = ++seq;
    try { history.replaceState({ ...(history.state ?? {}), guardList: { pid, q } }, ''); } catch { /* ignore */ }
    results.innerHTML = html`<div aria-busy="true" role="status"><span class="sr-only">Loading…</span>${[1, 2, 3].map(() => html`<div class="skeleton row"></div>`)}</div>`.toString();
    try {
      const { data } = await api.get(`/programs/${pid}/guardians${q ? `?q=${encodeURIComponent(q)}` : ''}`);
      if (!alive || mine !== seq) return;
      results.innerHTML = (data.length ? html`<ul class="list">${data.map((g) => html`<li><a class="row-link" href="${tab.link(g.id)}"><span class="avatar" aria-hidden="true">${initials(g.full_name)}</span>
          <span class="grow"><span class="t">${g.full_name}</span><span class="d block muted">${[g.relationship, g.phone].filter(Boolean).join(' · ') || 'No contact details'}</span></span>${icon('chevron', 'chev')}</a></li>`)}</ul>
        ${data.length >= 200 ? html`<p class="pager hint">Showing the first 200. Search to narrow the list.</p>` : ''}`
        : html`<div class="empty">${icon('user')}<b>${q ? 'No guardians match' : 'No guardians yet'}</b><span>${q ? 'Try a different name or phone number.' : canWrite ? 'Add a guardian, then choose them on a family or a child.' : 'Guardians appear here once they are added.'}</span>
          ${canWrite && !q ? html`<button class="btn" type="button" data-add>${icon('plus')} Add a guardian</button>` : ''}</div>`).toString();
    } catch (e) {
      if (alive && mine === seq) results.innerHTML = html`<div class="empty" role="alert">${icon('alert')}<b>The list could not be loaded</b><span>${api.explain(e)}</span><button class="btn" type="button" data-retry>Try again</button></div>`.toString();
    }
  };
  const typed = debounce((v) => { q = v.trim(); load(); }, 300);
  const onInput = (e) => { if (e.target.id === 'g-q') typed(e.target.value); };
  const onClick = (e) => {
    if (e.target.closest('[data-retry]')) load();
    if (e.target.closest('[data-add]')) guardianSheet(tab, null, (g) => tab.go(g.id));
  };
  tab.root.addEventListener('input', onInput);
  tab.root.addEventListener('click', onClick);
  load();
  return () => { alive = false; tab.root.removeEventListener('input', onInput); tab.root.removeEventListener('click', onClick); };
}

// ---- one guardian -------------------------------------------------------------------------------------------------
function mountDetail(tab, gid) {
  const pid = tab.program.id;
  const canWrite = tab.pcan('aytam.update');
  let alive = true;
  let g = null;
  let families = [];
  let children = [];
  let blocked = '';

  const start = async () => {
    tab.root.innerHTML = loadingView().toString();
    try {
      const { data } = await api.get(`/programs/${pid}/guardians/${gid}`);
      if (!alive) return;
      ({ families, children, ...g } = data);
      render();
    } catch (e) {
      if (alive) tab.root.innerHTML = (e.status === 404 ? errorView('It may have been removed.', { title: 'Guardian not found', retry: false, back: { href: tab.link(), label: 'Back to guardians' } }) : errorView(api.explain(e), { title: 'This guardian could not be opened' })).toString();
    }
  };

  const render = () => {
    tab.setTitle(g.full_name);
    const row = (k, v) => html`<dt>${k}</dt><dd>${v || html`<span class="muted">—</span>`}</dd>`;
    tab.root.innerHTML = html`<div class="stack-lg">
      <div class="page-head rec-head"><div class="rec-who"><span class="avatar lg" aria-hidden="true">${initials(g.full_name)}</span><div class="grow">
          <div class="crumb"><a href="${tab.link()}">Guardians</a>${icon('chevron')}<span>${g.full_name}</span></div><h2 class="rec-title">${g.full_name}</h2>
          <div class="prop">${g.relationship ? html`<span>${g.relationship}</span>` : ''}<span>${plural(children.length, 'child', 'children')}</span></div></div></div>
        ${canWrite ? html`<div class="btn-row"><button class="btn secondary" type="button" data-act="edit">${icon('edit')} Edit</button><button class="btn danger" type="button" data-act="delete">${icon('trash')} Delete</button></div>` : ''}</div>
      ${blocked ? html`<div class="banner warn" role="alert">${icon('alert')}<div class="grow">${blocked}</div></div>` : ''}
      <div class="rec-cols">
        <div class="rec-col">
          <section class="card flush o2"><div class="card-head"><h3>Children</h3></div>
            ${children.length ? html`<ul class="list">${children.map((c) => html`<li><a class="row-link" href="${href(`programs/${pid}/children/${c.id}`)}"><span class="grow"><span class="t">${c.name}</span><span class="d block muted">${c.aytam_code}</span></span>${aytamChip(c.status)}${icon('chevron', 'chev')}</a></li>`)}</ul>`
              : html`<div class="empty compact">${icon('heart')}<b>No children yet</b><span>Choose this guardian on a family or on a child’s page.</span></div>`}</section>
          ${families.length ? html`<section class="card flush o3"><div class="card-head"><h3>Families</h3></div><ul class="list">${families.map((f) => html`<li><a class="row-link" href="${href(`programs/${pid}/families/${f.id}`)}"><span class="avatar sq" aria-hidden="true">${icon('users')}</span><span class="grow t">${f.name}</span>${icon('chevron', 'chev')}</a></li>`)}</ul></section>` : ''}</div>
        <div class="rec-col"><section class="card o1"><div class="card-head"><h3>Contact</h3></div><dl class="kv">
          ${row('Phone', g.phone)}${row('Email', g.email)}${row('Address', g.address)}</dl></section>
          ${g.notes ? html`<section class="card o4"><div class="card-head"><h3>Notes</h3></div><p class="pre">${g.notes}</p></section>` : ''}</div></div></div>`.toString();
  };

  const onClick = async (e) => {
    if (e.target.closest('[data-retry]')) return start();
    const b = e.target.closest('[data-act]');
    if (!b) return;
    if (b.dataset.act === 'edit') guardianSheet(tab, g, (data) => { g = { ...g, ...data }; blocked = ''; render(); });
    if (b.dataset.act === 'delete') {
      if (!(await confirmDialog({ title: `Delete ${g.full_name}?`, text: 'The guardian is removed for good. This only works when no child or family still uses them.', confirmLabel: 'Delete guardian', danger: true }))) return;
      busy(b, true, 'Deleting…');
      try { await api.del(`/programs/${pid}/guardians/${gid}`); toast('Guardian deleted.'); tab.go(); } catch (err) {
        busy(b, false);
        blocked = err.status === 409 ? err.message : '';
        if (blocked) render(); else toast(api.explain(err), 'bad');
      }
    }
  };
  tab.root.addEventListener('click', onClick);
  start();
  return () => { alive = false; tab.root.removeEventListener('click', onClick); };
}
