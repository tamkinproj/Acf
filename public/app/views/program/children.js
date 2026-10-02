import * as api from '../../core/api.js';
import { apiUrl } from '../../core/config.js';
import { icon } from '../../core/icons.js';
import { href } from '../../core/router.js';
import { actionSheet, busy, confirmDialog, field, readForm, searchInput, select, sheet, showErrors, toast } from '../../core/ui.js';
import { $, $$, debounce, fmtDate, html, initials, plural, raw } from '../../core/util.js';

// Children (Aytam records) of one program: the list, the "add a child" form and one child's page.
//
//   #/programs/<id>/children                    list
//   #/programs/<id>/children/status/<status>    list filtered to one status
//   #/programs/<id>/children/new                add a child
//   #/programs/<id>/children/<recordId>         one child
//
// These screens talk to the server directly (no offline copy): approving, uploading and assigning only make sense online.
// The helpers exported below are shared with the families and guardians screens.

// ---- vocabulary ---------------------------------------------------------------------------------------------------
export const STATUS = {
  draft: ['grey', 'Draft', 'Still being filled in. It has not been sent for review.'],
  pending_review: ['orange', 'Pending review', 'Waiting for a reviewer to check it.'],
  needs_correction: ['red', 'Needs correction', 'Sent back. Fix what the note says, then submit it again.'],
  approved: ['blue', 'Approved', 'A reviewer approved it. Activate it when the child joins the program.'],
  active: ['green', 'Active', 'Part of the program.'],
  inactive: ['grey', 'Inactive', 'Paused. Not part of the program for now.'],
  archived: ['grey', 'Archived', 'Kept for the record. Restore it to make changes.'],
};
export const aytamChip = (s) => { const [tone, label] = STATUS[s] ?? ['grey', s]; return html`<span class="chip ${tone}">${label}</span>`; };

const DOC_TYPES = {
  passport: 'Passport', birth_certificate: 'Birth certificate', diploma: 'Diploma', transcript: 'School transcript', photo: 'Photo',
  medical_certificate: 'Medical certificate', police_clearance: 'Police clearance', recommendation_letter: 'Recommendation letter', other: 'Other document',
};
const typeLabel = (t) => DOC_TYPES[t] ?? String(t).replace(/_/g, ' ');
const DOC_STATUS = { pending: ['grey', 'Waiting for review'], verified: ['green', 'Verified'], rejected: ['red', 'Rejected'], expired: ['orange', 'Expired'] };
const SOURCES = { manual: 'Entered by hand', registration: 'Registration form', import: 'Imported from a file' };
const PARENT = { living: 'Living', deceased: 'Deceased', unknown: 'Not known' };
export const parentStatus = (s) => PARENT[s] ?? '';

// Every field of a child, in the order people expect it. `FIELD_EDITABLE` mirrors Aytam::FIELD_EDITABLE on the server
// (what someone who only sees assigned records may change); the server enforces it either way.
const F = {
  first_name: { label: 'First name', required: true },
  middle_name: { label: 'Middle name' },
  last_name: { label: 'Last name', required: true },
  arabic_name: { label: 'Name in Arabic', attrs: 'dir="auto"' },
  date_of_birth: { label: 'Date of birth', type: 'date' },
  gender: { label: 'Gender', options: [['', 'Not stated'], ['male', 'Male'], ['female', 'Female']] },
  nationality: { label: 'Nationality' },
  legacy_ref: { label: 'Old record number', hint: 'From an earlier system or paper file, if there is one.' },
  country: { label: 'Country' }, region: { label: 'Region' }, province: { label: 'Province' }, city: { label: 'City or town' }, barangay: { label: 'Barangay' },
  address_detail: { label: 'Street and house', area: true },
  education_level: { label: 'Level', hint: 'For example Elementary or High school.' }, grade: { label: 'Grade' }, school: { label: 'School' },
  education_notes: { label: 'Education notes', area: true },
  phone: { label: 'Phone', type: 'tel', autocomplete: 'tel' },
  email: { label: 'Email', type: 'email', autocomplete: 'email' },
};
const SECTIONS = [
  { title: 'Child', rows: [['first_name', 'last_name'], ['middle_name', 'arabic_name'], ['date_of_birth', 'gender'], ['nationality', 'legacy_ref']] },
  { title: 'Address', rows: [['country', 'region'], ['province', 'city'], ['barangay'], ['address_detail']] },
  { title: 'Education', rows: [['education_level', 'grade'], ['school'], ['education_notes']] },
  { title: 'Contact', rows: [['phone'], ['email']] },
];
const FIELD_EDITABLE = ['phone', 'email', 'country', 'region', 'province', 'city', 'barangay', 'address_detail', 'education_level', 'school', 'grade', 'education_notes'];

// ---- small shared helpers -----------------------------------------------------------------------------------------
/** A date without a time ("2012-04-17"), shown in the person's own locale and never shifted by a time zone. */
export function fmtDay(d) {
  if (!d) return '';
  const [y, m, day] = String(d).slice(0, 10).split('-').map(Number);
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(y, m - 1, day));
}
export function ageText(d) {
  if (!d) return '';
  const [y, m, day] = String(d).slice(0, 10).split('-').map(Number);
  const now = new Date();
  let years = now.getFullYear() - y;
  if (now.getMonth() + 1 < m || (now.getMonth() + 1 === m && now.getDate() < day)) years--;
  if (years < 0) return '';
  if (years < 1) { const months = Math.max(0, (now.getFullYear() - y) * 12 + now.getMonth() + 1 - m - (now.getDate() < day ? 1 : 0)); return plural(months, 'month'); }
  return plural(years, 'year');
}
const fmtSize = (n) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);
const dash = (v) => (v === null || v === undefined || v === '' ? html`<span class="muted">—</span>` : v);
const kvRow = (k, v) => html`<dt>${k}</dt><dd>${dash(v)}</dd>`;

export const area = ({ label, name, value = '', hint = '' }) => html`
  <div class="field"><label for="f-${name}">${label}</label><textarea class="textarea" id="f-${name}" name="${name}">${value ?? ''}</textarea>
  ${hint ? html`<span class="hint">${hint}</span>` : ''}<span class="err" data-err="${name}" hidden></span></div>`;

/** One input for a child field, from the descriptions above. */
const input = (name, value) => {
  const s = F[name];
  const base = { label: s.label, name, value, hint: s.hint };
  if (s.options) return select({ ...base, options: s.options });
  if (s.area) return area(base);
  return field({ ...base, type: s.type ?? 'text', required: s.required, autocomplete: s.autocomplete ?? 'off', attrs: s.type === 'date' ? `max="${new Date().toISOString().slice(0, 10)}" min="1900-01-02"` : s.attrs ?? '' });
};
const rowsHtml = (rows, values) => rows.map((r) => (r.length > 1 ? html`<div class="row-2">${r.map((n) => input(n, values[n]))}</div>` : input(r[0], values[r[0]])));

/** Show server validation errors beside their fields; true when at least one was placed. Moves focus to the first one. */
export function fieldErrors(form, errors) {
  $$('[aria-invalid]', form).forEach((el) => el.removeAttribute('aria-invalid'));
  const shown = showErrors(form, errors ?? {});
  if (!shown) return false;
  let first = null;
  for (const name of Object.keys(errors)) {
    const el = form.elements[name];
    if (el && form.querySelector(`[data-err="${CSS.escape(name)}"]`)) { el.setAttribute?.('aria-invalid', 'true'); first ||= el; }
  }
  first?.focus?.();
  return true;
}

/** Run a form's save: button busy, field errors inline, anything else as a toast. Resolves true when it worked. */
export async function submitForm(form, label, work) {
  const btn = $('button[type=submit]', form);
  busy(btn, true, label);
  try { await work(); return true; } catch (err) {
    if (!fieldErrors(form, err.errors)) toast(api.explain(err), 'bad');
    return false;
  } finally { busy(btn, false); }
}

export const loadingView = () => html`<div class="stack" aria-busy="true" role="status"><span class="sr-only">Loading…</span>
  <div class="skeleton title"></div><div class="card skeleton-card"><div class="skeleton line"></div><div class="skeleton line short"></div><div class="skeleton line"></div><div class="skeleton line short"></div></div></div>`;
export const errorView = (message, { title = 'This could not be loaded', retry = true, back = null } = {}) => html`<div class="card"><div class="empty" role="alert">${icon('alert')}<b>${title}</b><span>${message}</span>
  <div class="btn-row center">${retry ? html`<button class="btn" type="button" data-retry>Try again</button>` : ''}${back ? html`<a class="btn secondary" href="${back.href}">${back.label}</a>` : ''}</div></div></div>`;

// ---- searchable pickers (family / guardian) -----------------------------------------------------------------------
const KINDS = {
  family: { path: 'families', plural: 'families', map: (f) => ({ id: f.id, label: f.name, sub: [f.father_name, f.mother_name].filter(Boolean).join(' & ') }) },
  guardian: { path: 'guardians', plural: 'guardians', map: (g) => ({ id: g.id, label: g.full_name, sub: [g.relationship, g.phone].filter(Boolean).join(' · ') }) },
};
export const pickerMarkup = ({ name, kind, label, selected = null, noneLabel, hint = '' }) => html`
  <div class="field picker" data-picker="${name}" data-kind="${kind}" data-none="${noneLabel}">
    <span class="lbl" id="pk-l-${name}">${label}</span>
    <input type="hidden" name="${name}" value="${selected?.id ?? ''}">
    <div class="pick-now"><span class="grow" data-pick-now>${selected ? html`<b>${selected.label}</b>` : html`<span class="muted">${noneLabel}</span>`}</span>
      <button class="btn sm secondary" type="button" data-pick="" ${raw(selected ? '' : 'hidden')} data-pick-clear>Remove</button></div>
    ${searchInput({ id: `pk-q-${name}`, placeholder: `Find a ${kind}`, label: `Search ${KINDS[kind].plural}` })}
    <ul class="list pick-results" data-pick-results role="listbox" aria-labelledby="pk-l-${name}"></ul>
    ${hint ? html`<span class="hint">${hint}</span>` : ''}<span class="err" data-err="${name}" hidden></span></div>`;

/** Bring every picker inside `root` to life. */
export function initPickers(root, programId) {
  $$('[data-picker]', root).forEach((box) => {
    const kind = KINDS[box.dataset.kind];
    const hidden = $('input[type=hidden]', box);
    const now = $('[data-pick-now]', box);
    const clear = $('[data-pick-clear]', box);
    const list = $('[data-pick-results]', box);
    const search = $('input[type=search]', box);
    const none = box.dataset.none;
    let seq = 0;
    const draw = (items, total, q) => {
      const rows = items.slice(0, 8).map((o) => html`<li><button class="pick-opt" type="button" role="option" aria-selected="${o.id === hidden.value}" data-pick="${o.id}" data-label="${o.label}">
        <span class="grow"><span class="t">${o.label}</span>${o.sub ? html`<span class="d block">${o.sub}</span>` : ''}</span>${o.id === hidden.value ? icon('check') : ''}</button></li>`);
      list.innerHTML = html`${rows}${items.length === 0 ? html`<li class="pick-note">${q ? `No ${kind.plural} match “${q}”.` : `There are no ${kind.plural} yet.`}
          <a href="${href(`programs/${programId}/${kind.path}`)}" target="_blank" rel="noopener">Create ${box.dataset.kind === 'family' ? 'a family' : 'a guardian'} first</a> (opens in a new tab), then <button class="link" type="button" data-pick-reload>check again</button>.</li>` : ''}
        ${total > 8 ? html`<li class="pick-note">Showing 8 of ${total}. Type to narrow the list.</li>` : ''}`.toString();
    };
    const load = async () => {
      const mine = ++seq;
      const q = search.value.trim();
      list.setAttribute('aria-busy', 'true');
      try {
        const { data } = await api.get(`/programs/${programId}/${kind.path}${q ? `?q=${encodeURIComponent(q)}` : ''}`);
        if (mine === seq) draw(data.map(kind.map), data.length, q);
      } catch (e) {
        if (mine === seq) list.innerHTML = html`<li class="pick-note">${api.explain(e)} <button class="link" type="button" data-pick-reload>Try again</button></li>`.toString();
      } finally { if (mine === seq) list.removeAttribute('aria-busy'); }
    };
    const choose = (id, label) => {
      hidden.value = id;
      now.innerHTML = (id ? html`<b>${label}</b>` : html`<span class="muted">${none}</span>`).toString();
      clear.hidden = !id;
      $$('[data-pick]', list).forEach((b) => { b.setAttribute('aria-selected', String(b.dataset.pick === id)); });
      load();
    };
    box.addEventListener('click', (e) => {
      const opt = e.target.closest('[data-pick]');
      if (opt && opt.closest('[data-picker]') === box) choose(opt.dataset.pick, opt.dataset.label);
      if (e.target.closest('[data-pick-reload]')) load();
    });
    search.addEventListener('input', debounce(load, 250));
    load();
  });
}

// ---- router -------------------------------------------------------------------------------------------------------
export default {
  async mount(tab) {
    const [first, second] = tab.params;
    if (first === 'status') return mountList(tab, STATUS[second] ? second : '');
    if (first === 'new') return mountCreate(tab);
    if (first) return mountDetail(tab, first);
    return mountList(tab, '');
  },
};

const crumb = (tab, last) => html`<div class="crumb"><a href="${tab.link()}">Children</a>${icon('chevron')}<span>${last}</span></div>`;

// ---- the list -----------------------------------------------------------------------------------------------------
const SORTS = [['code', 'Code'], ['name', 'Name'], ['dob', 'Date of birth'], ['created', 'Recently added'], ['status', 'Status']];

function mountList(tab, urlStatus) {
  const pid = tab.program.id;
  const canAdd = tab.pcan('aytam.create');
  // The browser keeps this with the history entry, so "Back" from a child returns to the same search and page.
  const saved = history.state?.aytamList?.pid === pid ? history.state.aytamList : {};
  const st = { q: saved.q ?? '', status: urlStatus, gender: saved.gender ?? '', sort: saved.sort ?? 'code', dir: saved.dir ?? 'asc', page: saved.page ?? 1, per: saved.per ?? 25 };
  let alive = true;
  let seq = 0;

  tab.setTitle('Children');
  tab.root.innerHTML = html`<div class="stack-lg">
    <div class="page-head"><div><h2 class="section-title">Children</h2><p class="muted">${tab.pcan('aytam.view_all') ? 'Every child in this program.' : 'Showing the children assigned to you.'}</p></div>
      ${canAdd ? html`<div class="btn-row"><a class="btn" href="${tab.link('new')}">${icon('plus')} Add a child</a></div>` : ''}</div>
    <div class="card flush">
      <div class="filters" role="search">
        <div class="grow">${searchInput({ id: 'ch-q', value: st.q, placeholder: 'Search by name, code or old number', label: 'Search children' })}</div>
        <div class="field"><label class="sr-only" for="ch-status">Status</label><select class="select" id="ch-status"><option value="">All statuses</option>${Object.entries(STATUS).map(([v, [, t]]) => html`<option value="${v}" ${raw(st.status === v ? 'selected' : '')}>${t}</option>`)}</select></div>
        <div class="field"><label class="sr-only" for="ch-gender">Gender</label><select class="select" id="ch-gender">${[['', 'Boys and girls'], ['male', 'Boys'], ['female', 'Girls']].map(([v, t]) => html`<option value="${v}" ${raw(st.gender === v ? 'selected' : '')}>${t}</option>`)}</select></div>
        <div class="field sort-field"><label class="sr-only" for="ch-sort">Sort by</label><select class="select" id="ch-sort">${SORTS.map(([v, t]) => html`<option value="${v}" ${raw(st.sort === v ? 'selected' : '')}>Sort: ${t}</option>`)}</select>
          <button class="icon-btn" type="button" data-dir></button></div>
      </div>
      <div data-results aria-live="polite"></div>
    </div></div>`;
  const results = $('[data-results]', tab.root);
  const dirBtn = $('[data-dir]', tab.root);
  const paintDir = () => {
    dirBtn.innerHTML = icon(st.dir === 'asc' ? 'arrowUp' : 'arrowDown').toString();
    dirBtn.setAttribute('aria-label', st.dir === 'asc' ? 'Sorted ascending. Switch to descending' : 'Sorted descending. Switch to ascending');
  };
  paintDir();

  const persist = () => {
    try { history.replaceState({ ...(history.state ?? {}), aytamList: { pid, ...st } }, '', st.status ? tab.link(`status/${st.status}`) : tab.link()); } catch { /* history unavailable */ }
  };

  const rowsView = (rows, meta) => {
    const th = (key, label) => html`<th scope="col" aria-sort="${st.sort === key ? (st.dir === 'asc' ? 'ascending' : 'descending') : 'none'}"><button class="th" type="button" data-sort="${key}" aria-pressed="${st.sort === key}">${label}${st.sort === key ? icon(st.dir === 'asc' ? 'arrowUp' : 'arrowDown') : ''}</button></th>`;
    const from = (meta.page - 1) * meta.per_page + 1;
    return html`<table class="table child-table"><caption class="sr-only">Children</caption>
      <thead><tr>${th('name', 'Child')}${th('status', 'Status')}<th scope="col">City</th>${th('dob', 'Born')}</tr></thead>
      <tbody>${rows.map((c) => html`<tr data-edit="${c.id}">
        <td class="c-name"><div class="cell"><span class="avatar" aria-hidden="true">${initials(c.name)}</span><div class="grow">
          <a class="t row-name" href="${tab.link(c.id)}">${c.name}</a>
          <div class="d muted">${c.aytam_code}${c.arabic_name ? html` · <span dir="auto">${c.arabic_name}</span>` : ''}</div>
          <div class="d muted narrow-only">${[c.city, c.date_of_birth ? ageText(c.date_of_birth) : ''].filter(Boolean).join(' · ')}</div></div></div></td>
        <td class="c-status">${aytamChip(c.status)}</td>
        <td class="c-city">${dash(c.city)}</td>
        <td class="c-dob">${c.date_of_birth ? html`${fmtDay(c.date_of_birth)}<div class="d muted">${ageText(c.date_of_birth)}</div>` : dash('')}</td></tr>`)}</tbody></table>
      <div class="pager"><span class="muted" role="status">${meta.total === 0 ? '' : `${from}–${from + rows.length - 1} of ${meta.total}`}</span>
        <div class="btn-row"><label class="sr-only" for="ch-per">Rows per page</label><select class="select" id="ch-per">${[25, 50, 100].map((n) => html`<option value="${n}" ${raw(meta.per_page === n ? 'selected' : '')}>${n} per page</option>`)}</select>
          <button class="btn secondary sm" type="button" data-page="${meta.page - 1}" ${raw(meta.page <= 1 ? 'disabled' : '')}>Previous</button>
          <span class="muted pager-count">Page ${meta.page} of ${meta.last_page}</span>
          <button class="btn secondary sm" type="button" data-page="${meta.page + 1}" ${raw(meta.page >= meta.last_page ? 'disabled' : '')}>Next</button></div></div>`;
  };

  const emptyView = () => {
    const filtered = st.q || st.status || st.gender;
    return html`<div class="empty">${icon('heart')}<b>${filtered ? 'No children match' : tab.pcan('aytam.view_all') ? 'No children yet' : 'Nothing assigned to you yet'}</b>
      <span>${filtered ? 'Try a different search or filter.' : tab.pcan('aytam.view_all') ? (canAdd ? 'Add the first child to start the register.' : 'Children appear here once they are added.') : 'When a reviewer assigns you a child, they appear here.'}</span>
      ${filtered ? html`<button class="btn secondary" type="button" data-reset>Clear search and filters</button>` : canAdd ? html`<a class="btn" href="${tab.link('new')}">${icon('plus')} Add a child</a>` : ''}</div>`;
  };

  const load = async () => {
    const mine = ++seq;
    persist();
    if (!results.firstElementChild || results.querySelector('.empty')) results.innerHTML = html`<div aria-busy="true" role="status"><span class="sr-only">Loading…</span>${[1, 2, 3, 4].map(() => html`<div class="skeleton row"></div>`)}</div>`.toString();
    else results.classList.add('is-loading');
    const params = new URLSearchParams({ page: st.page, per_page: st.per, sort: st.sort, dir: st.dir });
    for (const k of ['q', 'status', 'gender']) if (st[k]) params.set(k, st[k]);
    try {
      const { data, meta } = await api.get(`/programs/${pid}/aytam?${params}`);
      if (!alive || mine !== seq) return;
      if (data.length === 0 && st.page > 1) { st.page = Math.max(1, meta.last_page); load(); return; }
      results.classList.remove('is-loading');
      results.innerHTML = (data.length ? rowsView(data, meta) : emptyView()).toString();
    } catch (e) {
      if (!alive || mine !== seq) return;
      results.classList.remove('is-loading');
      results.innerHTML = html`<div class="empty" role="alert">${icon('alert')}<b>The list could not be loaded</b><span>${api.explain(e)}</span><button class="btn" type="button" data-retry>Try again</button></div>`.toString();
    }
  };

  const change = (patch) => { Object.assign(st, patch, { page: patch.page ?? 1 }); load(); };
  const typed = debounce((value) => change({ q: value.trim() }), 300);
  const onInput = (e) => { if (e.target.id === 'ch-q') typed(e.target.value); };
  const onChange = (e) => {
    const id = e.target.id;
    if (id === 'ch-status') change({ status: e.target.value });
    if (id === 'ch-gender') change({ gender: e.target.value });
    if (id === 'ch-sort') { change({ sort: e.target.value, dir: 'asc' }); paintDir(); }
    if (id === 'ch-per') change({ per: Number(e.target.value) });
  };
  const onClick = (e) => {
    const t = e.target;
    const sortBtn = t.closest('[data-sort]');
    if (sortBtn) { const key = sortBtn.dataset.sort; change({ sort: key, dir: st.sort === key && st.dir === 'asc' ? 'desc' : 'asc' }); $('#ch-sort', tab.root).value = key; paintDir(); return; }
    if (t.closest('[data-dir]')) { change({ dir: st.dir === 'asc' ? 'desc' : 'asc', page: st.page }); paintDir(); return; }
    const pg = t.closest('[data-page]');
    if (pg) { st.page = Number(pg.dataset.page); load(); results.scrollIntoView?.({ block: 'start' }); return; }
    if (t.closest('[data-retry]')) { load(); return; }
    if (t.closest('[data-reset]')) {
      Object.assign(st, { q: '', status: '', gender: '', page: 1 });
      $('#ch-q', tab.root).value = ''; $('#ch-status', tab.root).value = ''; $('#ch-gender', tab.root).value = '';
      load(); return;
    }
    const tr = t.closest('tr[data-edit]');
    if (tr && !t.closest('a')) tab.go(tr.dataset.edit);
  };
  tab.root.addEventListener('input', onInput);
  tab.root.addEventListener('change', onChange);
  tab.root.addEventListener('click', onClick);
  load();
  return () => { alive = false; tab.root.removeEventListener('input', onInput); tab.root.removeEventListener('change', onChange); tab.root.removeEventListener('click', onClick); };
}

// ---- add a child --------------------------------------------------------------------------------------------------
function mountCreate(tab) {
  const pid = tab.program.id;
  tab.setTitle('Add a child');
  if (!tab.pcan('aytam.create')) {
    tab.root.innerHTML = html`<div class="card"><div class="empty">${icon('lock')}<b>You cannot add children</b><span>Ask a program supervisor if you need to.</span><a class="btn secondary" href="${tab.link()}">Back to children</a></div></div>`.toString();
    return;
  }
  const pickers = tab.pcan('aytam.view_all');
  const reviewer = tab.pcan('aytam.review');
  tab.root.innerHTML = html`<div class="stack-lg">
    <div class="page-head"><div>${crumb(tab, 'Add a child')}<h2 class="rec-title">Add a child</h2><p class="muted">Start with the name. Everything else can be added later.</p></div></div>
    <form class="form create-form" novalidate>
      <div class="banner bad" data-msg role="alert" hidden>${icon('alert')}<div class="grow">Some details need attention. They are marked below.</div></div>
      ${SECTIONS.map((s) => html`<section class="card"><h3 class="form-title">${s.title}</h3><div class="form">${rowsHtml(s.rows, {})}</div></section>`)}
      ${pickers ? html`<section class="card"><h3 class="form-title">Family and guardian</h3><div class="form">
        ${pickerMarkup({ name: 'family_id', kind: 'family', label: 'Family', noneLabel: 'Not linked to a family', hint: 'Brothers and sisters share a family record.' })}
        ${pickerMarkup({ name: 'guardian_id', kind: 'guardian', label: 'Guardian', noneLabel: 'Use the family’s guardian', hint: 'Only needed if this child’s guardian is not the family’s.' })}</div></section>` : ''}
      ${reviewer ? html`<section class="card"><h3 class="form-title">Where to start</h3><div class="form">${select({ label: 'Start as', name: 'status', value: 'draft', options: [['draft', 'Draft — to be reviewed later'], ['active', 'Active — already part of the program']] })}</div></section>` : ''}
      <div class="btn-row"><button class="btn" type="submit">Save child</button><a class="btn secondary" href="${tab.link()}">Cancel</a></div>
    </form></div>`;
  const form = $('form', tab.root);
  const msg = $('[data-msg]', form);
  initPickers(form, pid);
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    msg.hidden = true;
    const body = Object.fromEntries(Object.entries(readForm(form)).filter(([, v]) => v !== null && v !== ''));
    const ok = await submitForm(form, 'Saving…', async () => {
      const { data } = await api.post(`/programs/${pid}/aytam`, body);
      toast(`${data.name} was added.`);
      location.replace(tab.link(data.id));
    });
    msg.hidden = ok || !$('.err:not([hidden])', form);
    if (!msg.hidden) msg.scrollIntoView?.({ block: 'center' });
  });
}

// ---- one child ----------------------------------------------------------------------------------------------------
const MOVES = {
  pending_review: { label: 'Submit for review', rank: 1 },
  approved: { label: 'Approve', rank: 2, confirm: ['Approve this record?', 'Approving tells the team that the information is complete and correct.'] },
  active: { label: 'Activate', rank: 3 },
  draft: { label: 'Return to draft', rank: 4 },
  inactive: { label: 'Deactivate', rank: 5, confirm: ['Deactivate this child?', 'They will no longer count as part of the program. You can activate them again later.'] },
  needs_correction: { label: 'Send back for correction', rank: 6, note: true },
  archived: { label: 'Archive', rank: 7, danger: true, confirm: ['Archive this record?', 'It is kept for the record but can no longer be edited. You can restore it later.'] },
};
function moveFor(from, to) {
  const m = { ...MOVES[to] };
  if (from === 'archived' && to === 'inactive') return { label: 'Restore', rank: 1, confirm: ['Restore this record?', 'It comes back as Inactive. You can activate it from there.'] };
  if (from === 'pending_review' && to === 'draft') return { ...m, label: 'Take back to draft' };
  return m;
}

function mountDetail(tab, rid) {
  const pid = tab.program.id;
  const base = `/programs/${pid}`;
  const seeDocs = tab.pcan('documents.view');
  const links = tab.pcan('aytam.view_all');
  let alive = true;
  let d = null;
  let docs = null;          // { current, types } or null
  let docsFailed = false;

  const photoUrl = (doc, inline = true) => apiUrl(`${base}/documents/${doc.id}/download${inline ? '?inline=1' : ''}`);
  const downloadUrl = (doc) => apiUrl(`${base}/documents/${doc.id}/download`);

  const fetchAll = async () => {
    const [rec, dc] = await Promise.all([
      api.get(`${base}/aytam/${rid}`),
      seeDocs ? api.get(`${base}/aytam/${rid}/documents`).then((r) => r.data, () => 'failed') : null,
    ]);
    d = rec.data;
    docsFailed = dc === 'failed';
    docs = dc && dc !== 'failed' ? dc : null;
  };

  const start = async () => {
    tab.root.innerHTML = loadingView().toString();
    try { await fetchAll(); } catch (e) {
      if (!alive) return;
      tab.root.innerHTML = (e.status === 404
        ? errorView('It may have been removed, or it may not be assigned to you.', { title: 'Child not found', retry: false, back: { href: tab.link(), label: 'Back to children' } })
        : errorView(api.explain(e), { title: 'This child could not be opened' })).toString();
      return;
    }
    if (alive) render();
  };
  // After a change that can affect several cards (a document, say) read everything again, but keep the page in place.
  const refresh = async () => { try { await fetchAll(); if (alive) render(); } catch (e) { toast(api.explain(e), 'bad'); } };

  const render = () => {
    tab.setTitle(d.name);
    const photo = docs?.current.find((x) => x.type === 'photo' && x.status !== 'rejected') ?? d.documents.find((x) => x.type === 'photo' && x.status !== 'rejected');
    const canEditSomething = d.can.update && d.status !== 'archived';
    tab.root.innerHTML = html`<div class="stack-lg">
      <div class="page-head rec-head">
        <div class="rec-who">
          <span class="avatar lg ${photo && seeDocs ? 'photo' : ''}" aria-hidden="true">${photo && seeDocs ? html`<img src="${photoUrl(photo)}" alt="">` : initials(d.name)}</span>
          <div class="grow">${crumb(tab, d.aytam_code)}<h2 class="rec-title">${d.name}</h2>
            <div class="prop"><span class="chip">${d.aytam_code}</span>${aytamChip(d.status)}${d.fields.arabic_name ? html`<span dir="auto">${d.fields.arabic_name}</span>` : ''}</div></div></div>
        ${canEditSomething ? html`<div class="btn-row"><button class="btn secondary" type="button" data-act="edit">${icon('edit')} Edit details</button></div>` : ''}
      </div>
      ${d.status === 'needs_correction' && d.status_note ? html`<div class="banner warn" role="status">${icon('alert')}<div class="grow"><b>Sent back for correction.</b> ${d.status_note}</div></div>` : ''}
      <div class="rec-cols">
        <div class="rec-col">${detailsCard()}${seeDocs ? documentsCard() : ''}</div>
        <div class="rec-col">${statusCard()}${familyCard()}${assignCard()}</div>
      </div></div>`.toString();
  };

  // 1) the details themselves
  const detailsCard = () => {
    const f = d.fields;
    const show = (name) => {
      const v = f[name];
      if (name === 'date_of_birth') return v ? `${fmtDay(v)} (${ageText(v)})` : '';
      if (name === 'gender') return { male: 'Male', female: 'Female' }[v] ?? '';
      if (name === 'arabic_name') return v ? html`<span dir="auto">${v}</span>` : '';
      return v;
    };
    return html`<section class="card o1"><div class="card-head"><h3>Details</h3></div>
      ${SECTIONS.map((s) => html`<div class="kv-group"><h4>${s.title}</h4><dl class="kv">${s.rows.flat().map((n) => kvRow(F[n].label, show(n)))}</dl></div>`)}
      <div class="kv-group"><h4>Record</h4><dl class="kv">${kvRow('Added', fmtDate(d.created_at))}${kvRow('How', SOURCES[d.source] ?? d.source)}${d.approved_at ? kvRow('Approved', fmtDate(d.approved_at)) : ''}</dl></div></section>`;
  };

  // 2) status and workflow
  const statusCard = () => {
    const moves = d.can.transitions.map((to) => ({ to, ...moveFor(d.status, to) })).sort((a, b) => a.rank - b.rank);
    return html`<section class="card o2"><div class="card-head"><h3>Status</h3>${aytamChip(d.status)}</div>
      <p class="muted">${STATUS[d.status]?.[2] ?? ''}</p>
      ${d.status_note && d.status !== 'needs_correction' ? html`<p class="status-note"><span class="muted">Note:</span> ${d.status_note}</p>` : ''}
      ${moves.length ? html`<div class="btn-row">${moves.map((m, i) => html`<button class="btn ${m.danger ? 'danger' : i === 0 ? '' : 'secondary'}" type="button" data-act="move" data-to="${m.to}">${m.label}</button>`)}</div>`
        : html`<p class="hint">There is nothing for you to do at this stage.</p>`}</section>`;
  };

  // 3) family and guardian
  const familyCard = () => {
    const fam = d.family;
    const g = d.guardian;
    const canChange = d.can.update && d.can.edit_all && links && d.status !== 'archived';
    return html`<section class="card o3"><div class="card-head"><h3>Family and guardian</h3>${canChange ? html`<button class="btn sm secondary" type="button" data-act="links">Change</button>` : ''}</div>
      ${fam ? html`<dl class="kv"><dt>Family</dt><dd>${links ? html`<a href="${href(`programs/${pid}/families/${fam.id}`)}">${fam.name}</a>` : fam.name}</dd>
          ${fam.father_name ? kvRow('Father', html`${fam.father_name}${fam.father_status ? html` <span class="muted">· ${parentStatus(fam.father_status)}</span>` : ''}`) : ''}
          ${fam.mother_name ? kvRow('Mother', html`${fam.mother_name}${fam.mother_status ? html` <span class="muted">· ${parentStatus(fam.mother_status)}</span>` : ''}`) : ''}</dl>`
        : html`<p class="muted">Not linked to a family.</p>`}
      ${g ? html`<div class="guardian-box"><div class="card-sub"><b>Guardian</b>${g.inherited ? html` <span class="chip">Inherited from family</span>` : ''}</div>
          <dl class="kv"><dt>Name</dt><dd>${links ? html`<a href="${href(`programs/${pid}/guardians/${g.id}`)}">${g.full_name}</a>` : g.full_name}</dd>
            ${kvRow('Relationship', g.relationship)}${kvRow('Phone', g.phone)}${kvRow('Email', g.email)}</dl></div>`
        : html`<p class="muted guardian-none">No guardian recorded.</p>`}
      ${d.siblings.length ? html`<div class="card-sub"><b>Brothers and sisters</b></div><ul class="list">${d.siblings.map((s) => html`<li><a class="row-link" href="${tab.link(s.id)}"><span class="grow"><span class="t">${s.name}</span><span class="d block">${s.aytam_code}${s.date_of_birth ? ` · ${ageText(s.date_of_birth)}` : ''}</span></span>${aytamChip(s.status)}${icon('chevron', 'chev')}</a></li>`)}</ul>` : ''}
      ${links && (fam || g) ? html`<div class="btn-row card-links">${fam ? html`<a class="btn sm secondary" href="${href(`programs/${pid}/families/${fam.id}`)}">Open family</a>` : ''}${g ? html`<a class="btn sm secondary" href="${href(`programs/${pid}/guardians/${g.id}`)}">Open guardian</a>` : ''}</div>` : ''}
    </section>`;
  };

  // 4) documents
  const documentsCard = () => {
    const missing = d.required_documents.filter((r) => r.missing);
    const current = docs?.current ?? [];
    return html`<section class="card o4"><div class="card-head"><h3>Documents</h3>${d.can.upload ? html`<button class="btn sm" type="button" data-act="upload">${icon('upload')} Upload</button>` : ''}</div>
      ${d.required_documents.length ? html`<div class="card-sub"><b>Required</b></div>
        <ul class="list req-list">${d.required_documents.map((r) => html`<li><span class="grow t">${typeLabel(r.type)}</span>
          ${r.missing ? html`<span class="chip orange">Missing</span>${d.can.upload ? html`<button class="btn sm secondary" type="button" data-act="upload" data-type="${r.type}">Upload</button>` : ''}` : html`<span class="chip green">On file</span>`}</li>`)}</ul>
        ${missing.length === 0 ? html`<p class="hint">Every required document is on file.</p>` : ''}` : ''}
      <div class="card-sub"><b>On file</b></div>
      ${docsFailed ? html`<div class="banner bad" role="alert">${icon('alert')}<div class="grow">The documents could not be loaded.</div><button class="btn sm" type="button" data-act="reload-docs">Try again</button></div>`
        : current.length ? html`<ul class="list doc-list">${current.map(docItem)}</ul>`
          : html`<p class="muted">No documents yet.${d.can.upload ? ' Use Upload to add the first one.' : ''}</p>`}
    </section>`;
  };

  const docItem = (doc) => {
    const [tone, label] = DOC_STATUS[doc.status] ?? ['grey', doc.status];
    const isImage = String(doc.mime).startsWith('image/');
    const expired = doc.status === 'expired';
    return html`<li class="doc-item" data-doc="${doc.id}">
      ${isImage ? html`<a class="doc-thumb" href="${photoUrl(doc)}" target="_blank" rel="noopener" aria-label="Open ${typeLabel(doc.type)} full size"><img src="${photoUrl(doc)}" alt="${typeLabel(doc.type)}" loading="lazy"></a>` : html`<span class="doc-thumb" aria-hidden="true">${icon('folder')}</span>`}
      <div class="grow doc-body">
        <div class="doc-title"><span class="t">${typeLabel(doc.type)}</span><span class="chip ${tone}">${label}</span></div>
        <div class="d muted">${doc.original_name}</div>
        <div class="d muted">Version ${doc.doc_version}${doc.versions > 1 ? ` of ${doc.versions}` : ''} · ${fmtSize(doc.size)} · uploaded ${fmtDate(doc.uploaded_at)}${doc.expires_on ? html` · <span class="${expired ? 'exp-late' : ''}">${expired ? 'expired' : 'expires'} ${fmtDay(doc.expires_on)}</span>` : ''}</div>
        ${doc.status === 'rejected' && doc.rejection_reason ? html`<div class="doc-reason">${icon('alert')}<span><b>Rejected:</b> ${doc.rejection_reason}</span></div>` : ''}
        ${doc.notes ? html`<div class="d muted">Note: ${doc.notes}</div>` : ''}
        <div class="btn-row doc-actions">
          <a class="btn sm secondary" href="${downloadUrl(doc)}" download>Download</a>
          ${d.can.verify && doc.status !== 'verified' ? html`<button class="btn sm" type="button" data-act="verify" data-doc="${doc.id}">Verify</button>` : ''}
          ${d.can.verify && doc.status !== 'rejected' ? html`<button class="btn sm danger" type="button" data-act="reject" data-doc="${doc.id}">Reject</button>` : ''}
          ${d.can.upload && (doc.status === 'rejected' || doc.status === 'expired') ? html`<button class="btn sm secondary" type="button" data-act="upload" data-replace="${doc.id}">Upload a new version</button>` : ''}
          <button class="icon-btn" type="button" data-act="doc-more" data-doc="${doc.id}" aria-label="More for ${typeLabel(doc.type)}">${icon('more')}</button></div>
      </div></li>`;
  };

  // 5) field workers
  const assignCard = () => html`<section class="card o5"><div class="card-head"><h3>Assigned field workers</h3>${d.can.assign ? html`<button class="btn sm secondary" type="button" data-act="assign">${d.assignments.length ? 'Change' : 'Assign'}</button>` : ''}</div>
    ${d.assignments.length ? html`<ul class="list">${d.assignments.map((a) => html`<li><span class="avatar" aria-hidden="true">${initials(a.name)}</span><span class="t">${a.name}</span></li>`)}</ul>`
      : html`<p class="muted">No one is assigned to this child yet.</p>`}</section>`;

  // ---- actions ----
  const setRecord = (rec) => { d = rec; render(); };

  const doMove = async (btn) => {
    const to = btn.dataset.to;
    const m = moveFor(d.status, to);
    if (m.note) return askCorrection(to);
    if (m.confirm && !(await confirmDialog({ title: m.confirm[0], text: m.confirm[1], confirmLabel: m.label, danger: !!m.danger }))) return;
    busy(btn, true, 'Working…');
    try { const { data } = await api.post(`${base}/aytam/${rid}/status`, { status: to }); setRecord(data); toast(`${data.name}: ${STATUS[data.status]?.[1] ?? data.status}.`); }
    catch (e) { toast(api.explain(e), 'bad'); busy(btn, false); }
  };

  const askCorrection = (to) => sheet({
    title: 'Send back for correction',
    body: html`<form class="form" novalidate><p class="muted">Say what needs fixing. The person who entered the record will see this note.</p>
      ${area({ label: 'What needs to change?', name: 'note' })}
      <div class="btn-row"><button class="btn" type="submit">Send back</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => {
      const form = $('form', el);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const note = readForm(form).note;
        if (!note) { fieldErrors(form, { note: ['Say what needs to change.'] }); return; }
        const ok = await submitForm(form, 'Sending…', async () => { const { data } = await api.post(`${base}/aytam/${rid}/status`, { status: to, note }); close(); setRecord(data); toast('Sent back for correction.'); });
        if (!ok) return;
      });
    },
  });

  const openEdit = () => {
    const allowed = (n) => d.can.edit_all || FIELD_EDITABLE.includes(n);
    const sections = SECTIONS.map((s) => ({ title: s.title, rows: s.rows.map((r) => r.filter(allowed)).filter((r) => r.length) })).filter((s) => s.rows.length);
    const names = sections.flatMap((s) => s.rows.flat());
    sheet({
      title: 'Edit details',
      body: html`<form class="form" novalidate>
        ${d.can.edit_all ? '' : html`<div class="banner info">${icon('info')}<div class="grow">You can update the address, education and contact details.</div></div>`}
        ${sections.map((s) => html`<div class="form-section"><h4>${s.title}</h4><div class="form">${rowsHtml(s.rows, d.fields)}</div></div>`)}
        <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
      onMount: (el, close) => {
        const form = $('form', el);
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const v = readForm(form);
          const changed = Object.fromEntries(names.filter((n) => (v[n] ?? null) !== (d.fields[n] ?? null)).map((n) => [n, v[n] ?? null]));
          if (!Object.keys(changed).length) { close(); toast('Nothing changed.'); return; }
          await submitForm(form, 'Saving…', async () => { const { data } = await api.patch(`${base}/aytam/${rid}`, changed); close(); setRecord(data); toast('Saved.'); });
        });
      },
    });
  };

  const openLinks = () => sheet({
    title: 'Family and guardian',
    body: html`<form class="form" novalidate>
      ${pickerMarkup({ name: 'family_id', kind: 'family', label: 'Family', noneLabel: 'Not linked to a family', selected: d.family ? { id: d.family.id, label: d.family.name } : null })}
      ${pickerMarkup({ name: 'guardian_id', kind: 'guardian', label: 'Guardian', noneLabel: 'Use the family’s guardian', hint: 'Leave empty to use the family’s guardian.', selected: d.guardian && !d.guardian.inherited ? { id: d.guardian.id, label: d.guardian.full_name } : null })}
      <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => {
      const form = $('form', el);
      initPickers(form, pid);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const v = readForm(form);
        const changed = {};
        for (const n of ['family_id', 'guardian_id']) if ((v[n] ?? null) !== (d.fields[n] ?? null)) changed[n] = v[n] ?? null;
        if (!Object.keys(changed).length) { close(); toast('Nothing changed.'); return; }
        await submitForm(form, 'Saving…', async () => { const { data } = await api.patch(`${base}/aytam/${rid}`, changed); close(); setRecord(data); toast('Saved.'); });
      });
    },
  });

  const openAssign = () => {
    const box = sheet({ title: 'Assign field workers', body: html`<div class="stack" aria-busy="true"><div class="skeleton line"></div><div class="skeleton line short"></div></div>` });
    api.get(`${base}/aytam/assignable`).then(({ data }) => {
      const mine = new Set(d.assignments.map((a) => a.user_id));
      if (!data.length) { box.el.innerHTML = html`<div class="empty compact">${icon('users')}<b>No one to assign</b><span>Add people to this program’s team first.</span><a class="btn secondary" href="${href(`programs/${pid}/team`)}" data-close>Open the team</a></div>`.toString(); return; }
      box.el.innerHTML = html`<form class="form" novalidate><p class="muted">Pick everyone who should work on ${d.name}.</p>
        <fieldset class="assign-list"><legend class="sr-only">Team members</legend>${data.map((u) => html`<label class="check"><span class="grow"><span class="t">${u.name}</span>${u.role ? html`<span class="d block muted">${u.role}</span>` : ''}</span><input type="checkbox" name="user_ids" value="${u.user_id}" ${raw(mine.has(u.user_id) ? 'checked' : '')}></label>`)}</fieldset>
        <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`.toString();
      const form = $('form', box.el);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const ids = $$('input[name=user_ids]:checked', form).map((x) => x.value);
        await submitForm(form, 'Saving…', async () => { const { data: list } = await api.put(`${base}/aytam/${rid}/assignments`, { user_ids: ids }); d.assignments = list; box.close(); render(); toast('Assignments saved.'); });
      });
    }).catch((e) => { box.el.innerHTML = errorView(api.explain(e), { title: 'The team could not be loaded', retry: false }).toString(); });
  };

  const openUpload = ({ replaces = null, type = '' } = {}) => {
    const types = docs?.types ?? Object.keys(DOC_TYPES);
    const firstMissing = d.required_documents.find((r) => r.missing)?.type;
    const initial = replaces?.type ?? (type || firstMissing || 'other');
    sheet({
      title: replaces ? `New version: ${typeLabel(replaces.type)}` : 'Upload a document',
      body: html`<form class="form" novalidate>
        ${replaces ? html`<div class="banner info">${icon('info')}<div class="grow">This becomes version ${replaces.doc_version + 1}. The earlier version stays in the history.</div></div>`
          : select({ label: 'Kind of document', name: 'type', value: initial, options: types.map((t) => [t, typeLabel(t)]) })}
        <div class="field"><label for="f-file">File</label><input class="input" id="f-file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
          <span class="hint" data-file-hint>A PDF, or a photo (JPEG, PNG or WebP).</span><span class="err" data-err="file" hidden></span></div>
        ${field({ label: 'Expires on (optional)', name: 'expires_on', type: 'date', attrs: `min="${new Date(Date.now() + 864e5).toISOString().slice(0, 10)}"`, hint: 'For documents that run out, such as a passport.' })}
        ${area({ label: 'Note (optional)', name: 'notes' })}
        <div class="btn-row"><button class="btn" type="submit">Upload</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
      onMount: (el, close) => {
        const form = $('form', el);
        const fileInput = $('#f-file', form);
        const typeSel = $('#f-type', form);
        const syncAccept = () => {
          const photo = (replaces?.type ?? typeSel?.value) === 'photo';
          fileInput.accept = photo ? '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp' : '.pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp';
          $('[data-file-hint]', form).textContent = photo ? 'A photo (JPEG, PNG or WebP). It becomes the current photo.' : 'A PDF, or a photo (JPEG, PNG or WebP).';
        };
        typeSel?.addEventListener('change', syncAccept);
        syncAccept();
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const file = fileInput.files[0];
          if (!file) { fieldErrors(form, { file: ['Choose a file to upload.'] }); return; }
          const fd = new FormData();
          fd.set('file', file);
          fd.set('type', replaces?.type ?? typeSel.value);
          if (replaces) fd.set('replaces', replaces.id);
          const v = readForm(form);
          if (v.expires_on) fd.set('expires_on', v.expires_on);
          if (v.notes) fd.set('notes', v.notes);
          await submitForm(form, 'Uploading…', async () => { await api.post(`${base}/aytam/${rid}/documents`, fd, { timeout: 120000 }); close(); toast('Uploaded.'); await refresh(); });
        });
      },
    });
  };

  const openHistory = (doc) => {
    const box = sheet({ title: `History: ${typeLabel(doc.type)}`, body: html`<div class="stack" aria-busy="true"><div class="skeleton line"></div><div class="skeleton line short"></div></div>` });
    api.get(`${base}/documents/${doc.id}/history`).then(({ data }) => {
      box.el.innerHTML = html`<ul class="list history-list">${data.map((v) => { const [tone, label] = DOC_STATUS[v.status] ?? ['grey', v.status]; return html`<li><div class="grow"><div class="doc-title"><span class="t">Version ${v.doc_version}</span>${v.is_current ? html`<span class="chip blue">Current</span>` : ''}<span class="chip ${tone}">${label}</span></div>
          <div class="d muted">${v.original_name} · ${fmtSize(v.size)}</div><div class="d muted">Uploaded ${fmtDate(v.uploaded_at)}${v.expires_on ? ` · expires ${fmtDay(v.expires_on)}` : ''}</div>
          ${v.rejection_reason ? html`<div class="d">Rejected: ${v.rejection_reason}</div>` : ''}</div>
          <a class="btn sm secondary" href="${downloadUrl(v)}" download>Download</a></li>`; })}</ul>`.toString();
    }).catch((e) => { box.el.innerHTML = errorView(api.explain(e), { retry: false }).toString(); });
  };

  const openDecision = (doc, decision) => {
    const reject = decision === 'rejected';
    sheet({
      title: `${reject ? 'Reject' : 'Verify'}: ${typeLabel(doc.type)}`,
      body: html`<form class="form" novalidate>
        <p class="muted">${doc.original_name}</p>
        ${reject ? area({ label: 'Why is it rejected?', name: 'reason', hint: 'The uploader sees this and can send a new version.' })
          : field({ label: 'Expires on (optional)', name: 'expires_on', type: 'date', value: doc.expires_on ?? '', hint: 'Set or correct the date if the document runs out.' })}
        <div class="btn-row"><button class="btn ${reject ? 'danger' : ''}" type="submit">${reject ? 'Reject document' : 'Verify document'}</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
      onMount: (el, close) => {
        const form = $('form', el);
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const v = readForm(form);
          if (reject && !v.reason) { fieldErrors(form, { reason: ['Say why this document is rejected.'] }); return; }
          const body = reject ? { decision: decision, reason: v.reason } : { decision, ...(v.expires_on ? { expires_on: v.expires_on } : {}) };
          await submitForm(form, 'Saving…', async () => { await api.post(`${base}/documents/${doc.id}/decision`, body); close(); toast(reject ? 'Document rejected.' : 'Document verified.'); await refresh(); });
        });
      },
    });
  };

  const onClick = (e) => {
    const b = e.target.closest('[data-act]');
    if (b) {
      const doc = b.dataset.doc ? docs?.current.find((x) => x.id === b.dataset.doc) : null;
      switch (b.dataset.act) {
        case 'edit': openEdit(); break;
        case 'move': doMove(b); break;
        case 'links': openLinks(); break;
        case 'assign': openAssign(); break;
        case 'upload': openUpload({ replaces: b.dataset.replace ? docs?.current.find((x) => x.id === b.dataset.replace) : null, type: b.dataset.type }); break;
        case 'verify': if (doc) openDecision(doc, 'verified'); break;
        case 'reject': if (doc) openDecision(doc, 'rejected'); break;
        case 'reload-docs': refresh(); break;
        case 'doc-more': if (doc) actionSheet({ title: typeLabel(doc.type), actions: [
          ...(d.can.upload ? [{ label: 'Upload a new version', icon: 'upload', run: () => openUpload({ replaces: doc }) }] : []),
          { label: 'Version history', icon: 'list', run: () => openHistory(doc) },
        ] }); break;
        default:
      }
      return;
    }
    if (e.target.closest('[data-retry]')) start();
  };
  tab.root.addEventListener('click', onClick);
  start();
  return () => { alive = false; tab.root.removeEventListener('click', onClick); };
}
