import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { href } from '../../core/router.js';
import { busy, confirmDialog, field, readForm, searchInput, select, sheet, toast } from '../../core/ui.js';
import { $, debounce, html, plural } from '../../core/util.js';
import { aytamChip, ageText, area, errorView, initPickers, loadingView, parentStatus, pickerMarkup, submitForm } from './children.js';

// Families: one household record that several children can share (parents, address, a guardian).
//   #/programs/<id>/families            list
//   #/programs/<id>/families/<familyId> one family, with its children
export default {
  async mount(tab) {
    const [id] = tab.params;
    return id ? mountDetail(tab, id) : mountList(tab);
  },
};

const PARENT_OPTIONS = [['', 'Not stated'], ['living', 'Living'], ['deceased', 'Deceased'], ['unknown', 'Not known']];
const parents = (f) => [f.father_name, f.mother_name].filter(Boolean).join(' & ');

// ---- create / edit ------------------------------------------------------------------------------------------------
function familySheet(tab, family, done) {
  const pid = tab.program.id;
  const f = family ?? {};
  const keys = ['name', 'father_name', 'father_status', 'mother_name', 'mother_status', 'phone', 'country', 'region', 'province', 'city', 'barangay', 'address_detail', 'notes'];
  sheet({
    title: family ? 'Edit family' : 'Add a family',
    body: html`<form class="form" novalidate>
      ${field({ label: 'Family name', name: 'name', value: f.name, required: true, hint: 'How people will find it, for example “Abdullah family”.' })}
      <div class="form-section"><h4>Parents</h4><div class="form">
        ${field({ label: 'Father', name: 'father_name', value: f.father_name })}
        ${select({ label: 'Father is', name: 'father_status', value: f.father_status ?? '', options: PARENT_OPTIONS })}
        ${field({ label: 'Mother', name: 'mother_name', value: f.mother_name })}
        ${select({ label: 'Mother is', name: 'mother_status', value: f.mother_status ?? '', options: PARENT_OPTIONS })}</div></div>
      <div class="form-section"><h4>Guardian</h4>${pickerMarkup({ name: 'guardian_id', kind: 'guardian', label: 'Who looks after the children', noneLabel: 'No guardian', selected: f.guardian ? { id: f.guardian.id, label: f.guardian.full_name } : null })}</div>
      <div class="form-section"><h4>Address and contact</h4><div class="form">
        ${field({ label: 'Phone', name: 'phone', type: 'tel', value: f.phone, autocomplete: 'tel' })}
        <div class="row-2">${field({ label: 'Country', name: 'country', value: f.country })}${field({ label: 'Region', name: 'region', value: f.region })}</div>
        <div class="row-2">${field({ label: 'Province', name: 'province', value: f.province })}${field({ label: 'City or town', name: 'city', value: f.city })}</div>
        ${field({ label: 'Barangay', name: 'barangay', value: f.barangay })}
        ${area({ label: 'Street and house', name: 'address_detail', value: f.address_detail })}
        ${area({ label: 'Notes', name: 'notes', value: f.notes })}</div></div>
      <div class="btn-row"><button class="btn" type="submit">${family ? 'Save' : 'Add family'}</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => {
      const form = $('form', el);
      initPickers(form, pid);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const v = readForm(form);
        const names = [...keys, 'guardian_id'];
        const orig = family ? { ...family, guardian_id: family.guardian_id ?? null } : {};
        const body = family
          ? Object.fromEntries(names.filter((n) => (v[n] ?? null) !== (orig[n] ?? null)).map((n) => [n, v[n] ?? null]))
          : Object.fromEntries(names.filter((n) => v[n] !== null && v[n] !== undefined).map((n) => [n, v[n]]));
        if (family && !Object.keys(body).length) { close(); toast('Nothing changed.'); return; }
        await submitForm(form, 'Saving…', async () => {
          const { data } = family ? await api.patch(`/programs/${pid}/families/${family.id}`, body) : await api.post(`/programs/${pid}/families`, body);
          close();
          toast(family ? 'Saved.' : `${data.name} was added.`);
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
  const saved = history.state?.famList?.pid === pid ? history.state.famList.q : '';
  let q = saved ?? '';
  let seq = 0;
  let alive = true;
  tab.setTitle('Families');
  tab.root.innerHTML = html`<div class="stack-lg">
    <div class="page-head"><div><h2 class="section-title">Families</h2><p class="muted">Brothers and sisters share one family record: parents, address and guardian.</p></div>
      ${canWrite ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} Add a family</button></div>` : ''}</div>
    <div class="card flush"><div class="filters" role="search"><div class="grow">${searchInput({ id: 'fam-q', value: q, placeholder: 'Search by family or parent name', label: 'Search families' })}</div></div>
      <div data-results aria-live="polite"></div></div></div>`;
  const results = $('[data-results]', tab.root);

  const load = async () => {
    const mine = ++seq;
    try { history.replaceState({ ...(history.state ?? {}), famList: { pid, q } }, ''); } catch { /* ignore */ }
    results.innerHTML = html`<div aria-busy="true" role="status"><span class="sr-only">Loading…</span>${[1, 2, 3].map(() => html`<div class="skeleton row"></div>`)}</div>`.toString();
    try {
      const { data } = await api.get(`/programs/${pid}/families${q ? `?q=${encodeURIComponent(q)}` : ''}`);
      if (!alive || mine !== seq) return;
      results.innerHTML = (data.length ? html`<ul class="list">${data.map((f) => html`<li><a class="row-link" href="${tab.link(f.id)}"><span class="avatar sq" aria-hidden="true">${icon('users')}</span>
          <span class="grow"><span class="t">${f.name}</span><span class="d block muted">${[parents(f), f.city].filter(Boolean).join(' · ') || 'No parents recorded'}</span>${f.guardian ? html`<span class="d block muted">Guardian: ${f.guardian.full_name}</span>` : ''}</span>
          <span class="chip ${f.members_count ? 'blue' : ''}">${plural(f.members_count ?? 0, 'child', 'children')}</span>${icon('chevron', 'chev')}</a></li>`)}</ul>
        ${data.length >= 200 ? html`<p class="pager hint">Showing the first 200. Search to narrow the list.</p>` : ''}`
        : html`<div class="empty">${icon('users')}<b>${q ? 'No families match' : 'No families yet'}</b><span>${q ? 'Try a different search.' : canWrite ? 'Add a family, then link children to it from their page.' : 'Families appear here once they are added.'}</span>
          ${canWrite && !q ? html`<button class="btn" type="button" data-add>${icon('plus')} Add a family</button>` : ''}</div>`).toString();
    } catch (e) {
      if (alive && mine === seq) results.innerHTML = html`<div class="empty" role="alert">${icon('alert')}<b>The list could not be loaded</b><span>${api.explain(e)}</span><button class="btn" type="button" data-retry>Try again</button></div>`.toString();
    }
  };
  const typed = debounce((v) => { q = v.trim(); load(); }, 300);
  const onInput = (e) => { if (e.target.id === 'fam-q') typed(e.target.value); };
  const onClick = (e) => {
    if (e.target.closest('[data-retry]')) load();
    if (e.target.closest('[data-add]')) familySheet(tab, null, (f) => tab.go(f.id));
  };
  tab.root.addEventListener('input', onInput);
  tab.root.addEventListener('click', onClick);
  load();
  return () => { alive = false; tab.root.removeEventListener('input', onInput); tab.root.removeEventListener('click', onClick); };
}

// ---- one family ---------------------------------------------------------------------------------------------------
function mountDetail(tab, fid) {
  const pid = tab.program.id;
  const canWrite = tab.pcan('aytam.update');
  let alive = true;
  let fam = null;
  let members = [];
  let blocked = '';

  const start = async () => {
    tab.root.innerHTML = loadingView().toString();
    try {
      const { data } = await api.get(`/programs/${pid}/families/${fid}`);
      if (!alive) return;
      ({ members, ...fam } = data);
      render();
    } catch (e) {
      if (alive) tab.root.innerHTML = (e.status === 404 ? errorView('It may have been removed.', { title: 'Family not found', retry: false, back: { href: tab.link(), label: 'Back to families' } }) : errorView(api.explain(e), { title: 'This family could not be opened' })).toString();
    }
  };

  const render = () => {
    tab.setTitle(fam.name);
    const row = (k, v) => html`<dt>${k}</dt><dd>${v || html`<span class="muted">—</span>`}</dd>`;
    const person = (name, status) => (name ? html`${name}${status ? html` <span class="muted">· ${parentStatus(status)}</span>` : ''}` : '');
    const address = [fam.address_detail, fam.barangay, fam.city, fam.province, fam.region, fam.country].filter(Boolean).join(', ');
    tab.root.innerHTML = html`<div class="stack-lg">
      <div class="page-head"><div><div class="crumb"><a href="${tab.link()}">Families</a>${icon('chevron')}<span>${fam.name}</span></div><h2 class="rec-title">${fam.name}</h2>
          <div class="prop"><span>${plural(members.length, 'child', 'children')}</span>${fam.city ? html`<span>${fam.city}</span>` : ''}</div></div>
        ${canWrite ? html`<div class="btn-row"><button class="btn secondary" type="button" data-act="edit">${icon('edit')} Edit</button><button class="btn danger" type="button" data-act="delete">${icon('trash')} Delete</button></div>` : ''}</div>
      ${blocked ? html`<div class="banner warn" role="alert">${icon('alert')}<div class="grow">${blocked}</div></div>` : ''}
      <div class="rec-cols">
        <div class="rec-col"><section class="card flush o2"><div class="card-head"><h3>Children</h3></div>
          ${members.length ? html`<ul class="list">${members.map((m) => html`<li><a class="row-link" href="${href(`programs/${pid}/children/${m.id}`)}"><span class="grow"><span class="t">${m.name}</span><span class="d block muted">${m.aytam_code}${m.date_of_birth ? ` · ${ageText(m.date_of_birth)}` : ''}</span></span>${aytamChip(m.status)}${icon('chevron', 'chev')}</a></li>`)}</ul>`
            : html`<div class="empty compact">${icon('heart')}<b>No children yet</b><span>Link a child to this family from the child’s page.</span></div>`}</section></div>
        <div class="rec-col">
          <section class="card o1"><div class="card-head"><h3>Parents and guardian</h3></div><dl class="kv">
            ${row('Father', person(fam.father_name, fam.father_status))}${row('Mother', person(fam.mother_name, fam.mother_status))}
            ${row('Guardian', fam.guardian ? html`<a href="${href(`programs/${pid}/guardians/${fam.guardian.id}`)}">${fam.guardian.full_name}</a>` : '')}</dl></section>
          <section class="card o3"><div class="card-head"><h3>Address and contact</h3></div><dl class="kv">${row('Phone', fam.phone)}${row('Address', address)}</dl></section>
          ${fam.notes ? html`<section class="card o4"><div class="card-head"><h3>Notes</h3></div><p class="pre">${fam.notes}</p></section>` : ''}</div></div></div>`.toString();
  };

  const onClick = async (e) => {
    const b = e.target.closest('[data-act]');
    if (e.target.closest('[data-retry]')) return start();
    if (!b) return;
    if (b.dataset.act === 'edit') familySheet(tab, fam, (data) => { fam = { ...fam, ...data }; blocked = ''; render(); });
    if (b.dataset.act === 'delete') {
      if (!(await confirmDialog({ title: `Delete ${fam.name}?`, text: 'The family record is removed for good. This only works when no children are linked to it.', confirmLabel: 'Delete family', danger: true }))) return;
      busy(b, true, 'Deleting…');
      try { await api.del(`/programs/${pid}/families/${fid}`); toast('Family deleted.'); tab.go(); } catch (err) {
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
