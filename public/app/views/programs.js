import * as api from '../core/api.js';
import { icon } from '../core/icons.js';
import { href, navigate } from '../core/router.js';
import { busy, field, readForm, searchInput, select, sheet, showErrors, textarea, toast } from '../core/ui.js';
import { $, fmtDate, html, plural } from '../core/util.js';

export const STATUS_CHIP = { draft: ['grey', 'Draft'], active: ['green', 'Active'], inactive: ['orange', 'Inactive'], archived: ['grey', 'Archived'] };
export const statusChip = (s) => { const [tone, label] = STATUS_CHIP[s] ?? ['grey', s]; return html`<span class="chip ${tone}">${label}</span>`; };

// Programs: what the foundation does. The list is the same for every kind of program; opening one shows its own workspace.
export default {
  async mount(ctx) {
    if (ctx.params[0]) return (await import('./program/workspace.js')).default.mount(ctx);

    let programs = [];
    let types = [];
    let q = '';
    let status = 'live';
    let failed = null;
    const create = ctx.can('programs.create');

    const draw = () => {
      const term = q.toLowerCase();
      const shown = programs.filter((p) => (status === 'live' ? p.status !== 'archived' : p.status === status) && (!term || `${p.name} ${p.category_label}`.toLowerCase().includes(term)));
      ctx.root.innerHTML = view(shown, programs.length, { q, status, create, failed }).toString();
    };
    const load = async () => {
      try {
        const [list, t] = await Promise.all([api.get('/programs?status=' + (status === 'archived' ? 'archived' : '')), types.length ? { data: types } : api.get('/program-types')]);
        programs = list.data; types = t.data; failed = null;
      } catch (e) { failed = api.explain(e); }
      draw();
    };

    ctx.root.addEventListener('input', (e) => { if (e.target.id === 'q') { q = e.target.value; draw(); const i = $('#q', ctx.root); i.focus(); i.setSelectionRange(q.length, q.length); } });
    ctx.root.addEventListener('change', (e) => { if (e.target.id === 'st') { status = e.target.value; load(); } });
    ctx.root.addEventListener('click', (e) => {
      if (e.target.closest('[data-add]')) openCreate(types);
      if (e.target.closest('[data-retry]')) load();
    });
    await load();
  },
};

function openCreate(types) {
  sheet({
    title: 'New program',
    body: html`<form class="form" novalidate>
      ${field({ label: 'Program name', name: 'name', required: true, hint: 'For example "Aytam care" or "Ramadan food packs".' })}
      ${select({ label: 'Kind of program', name: 'category', value: 'aytam', options: types.map((t) => [t.category, t.available ? t.label : `${t.label} (basic)`]), hint: 'Aytam has everything built in. Other kinds start as a simple program you can grow later.' })}
      ${textarea({ label: 'Description', name: 'description' })}
      <div class="row-2">${field({ label: 'Starts', name: 'start_date', type: 'date' })}${field({ label: 'Ends', name: 'end_date', type: 'date' })}</div>
      <div class="btn-row"><button class="btn" type="submit">Create program</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Creating…');
      try {
        const { data } = await api.post('/programs', readForm(form));
        close();
        toast('Program created. Activate it when it is ready.');
        navigate(`programs/${data.id}`);
      } catch (err) {
        if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad');
      } finally { busy(btn, false); }
    }),
  });
}

function view(programs, total, { q, status, create, failed }) {
  return html`
    <div class="page-head"><div><h1>Programs</h1><p>Everything the foundation does is a program. Each one has its own people, records and partners.</p></div>
      ${create ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} New program</button></div>` : ''}</div>
    ${failed ? html`<div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>` : ''}
    <div class="card flush"><div class="filters">
      <div class="grow">${searchInput({ value: q, placeholder: 'Search programs', label: 'Search programs' })}</div>
      <div class="field"><label class="sr-only" for="st">Show</label><select class="select" id="st">${[['live', 'Current'], ['active', 'Active only'], ['draft', 'Drafts'], ['inactive', 'Inactive'], ['archived', 'Archived']].map(([v, t]) => html`<option value="${v}" ${status === v ? 'selected' : ''}>${t}</option>`)}</select></div></div>
      ${programs.length ? html`<ul class="list program-list">${programs.map((p) => html`<li><a class="row-link" href="${href('programs/' + p.id)}">
        <span class="avatar sq" aria-hidden="true">${icon(p.category === 'aytam' ? 'heart' : p.category === 'education' ? 'flag' : p.category === 'relief' || p.category === 'food_distribution' ? 'box' : 'hand')}</span>
        <span class="grow"><span class="t">${p.name}</span><span class="d block">${p.category_label}${p.start_date ? ` · from ${fmtDate(p.start_date)}` : ''}</span></span>
        ${statusChip(p.status)}${icon('chevron', 'chev')}</a></li>`)}</ul>`
        : html`<div class="empty">${icon('heart')}<b>${total ? 'No programs match' : 'No programs yet'}</b><span>${total ? 'Try a different search or filter.' : create ? 'Create the first program to start working.' : 'You have not been added to any program yet.'}</span>${create && !total ? html`<button class="btn" type="button" data-add>${icon('plus')} New program</button>` : ''}</div>`}
    </div>`;
}
