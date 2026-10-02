import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { href } from '../../core/router.js';
import { ago, fmtDate, html } from '../../core/util.js';
import { statusChip } from '../programs.js';

// The first tab of a program. An Aytam program shows where its children stand; a program of a kind that has no screens
// of its own yet shows a calm summary and points to the parts every program already has.
export default {
  async mount(tab) {
    return tab.program.module === 'aytam' ? aytamOverview(tab) : genericOverview(tab);
  },
};

// ---- Aytam --------------------------------------------------------------------------------------------------------
function aytamOverview(tab) {
  const pid = tab.program.id;
  let alive = true;

  const skeleton = () => html`<div class="stack" aria-busy="true" role="status"><span class="sr-only">Loading…</span>
    <div class="overview">${[1, 2, 3, 4].map(() => html`<div class="metric"><div class="skeleton line short"></div><div class="skeleton title"></div></div>`)}</div>
    <div class="card skeleton-card"><div class="skeleton line"></div><div class="skeleton line short"></div><div class="skeleton line"></div></div></div>`;

  const draw = (d) => {
    const list = (status) => href(`programs/${pid}/children${status ? `/status/${status}` : ''}`);
    const metrics = [
      { k: 'Children', v: d.total, s: d.sees_all ? 'in this program' : 'assigned to you', to: list('') },
      { k: 'Active', v: d.active, s: 'part of the program', to: list('active') },
      { k: 'Pending review', v: d.pending_review, s: 'waiting for a reviewer', to: list('pending_review') },
      { k: 'Needs correction', v: d.needs_correction, s: 'sent back', to: list('needs_correction') },
      { k: 'Missing documents', v: d.missing_documents, s: 'children affected', to: list('') },
      d.pending_registrations !== null && d.pending_registrations !== undefined && { k: 'Registrations waiting', v: d.pending_registrations, s: 'applications to review', to: href(`programs/${pid}/registration`) },
    ].filter(Boolean);
    const actions = [
      tab.pcan('aytam.create') && { label: 'Add a child', note: 'Start a new record', icon: 'plus', to: href(`programs/${pid}/children/new`) },
      tab.pcan('aytam.review') && { label: 'Review registrations', note: 'Applications waiting for a decision', icon: 'list', to: href(`programs/${pid}/registration`) },
      tab.pcan('aytam.import') && { label: 'Import', note: 'Bring in existing records from a file', icon: 'upload', to: href(`programs/${pid}/import`) },
    ].filter(Boolean);

    tab.root.innerHTML = html`<div class="stack-lg">
      ${d.sees_all ? '' : html`<p class="muted scope-note">${icon('info')} Showing the children assigned to you.</p>`}
      <div class="overview n${metrics.length}" role="list" aria-label="Summary">${metrics.map((m) => html`<a class="metric" role="listitem" href="${m.to}"><span class="k">${m.k}</span><span class="v">${m.v}</span><span class="s">${m.s}</span></a>`)}</div>
      <div class="two-col">
        <section class="card flush"><div class="card-head"><h3>Recent activity</h3></div>
          ${d.recent_activity.length ? html`<ul class="list">${d.recent_activity.map((a) => html`<li><span class="dot grey" aria-hidden="true"></span><div class="grow"><div class="t">${a.summary}</div><div class="d muted">${a.user_name ? `${a.user_name} · ` : ''}${ago(a.occurred_at)}</div></div></li>`)}</ul>`
            : html`<div class="empty compact">${icon('list')}<b>Nothing yet</b><span>Changes to children, families and documents show up here.</span></div>`}</section>
        ${actions.length ? html`<section class="card"><div class="card-head"><h3>Quick actions</h3></div><ul class="list">${actions.map((a) => html`<li><a class="row-link" href="${a.to}">${icon(a.icon)}<span class="grow"><span class="t">${a.label}</span><span class="d block muted">${a.note}</span></span>${icon('chevron', 'chev')}</a></li>`)}</ul></section>` : ''}
      </div></div>`.toString();
  };

  const load = async () => {
    tab.root.innerHTML = skeleton().toString();
    try {
      const { data } = await api.get(`/programs/${pid}/aytam-dashboard`);
      if (alive) draw(data);
    } catch (e) {
      if (!alive) return;
      tab.root.innerHTML = html`<div class="card"><div class="empty" role="alert">${icon('alert')}<b>The overview could not be loaded</b><span>${api.explain(e)}</span><button class="btn" type="button" data-retry>Try again</button></div></div>`.toString();
    }
  };
  const onClick = (e) => { if (e.target.closest('[data-retry]')) load(); };
  tab.root.addEventListener('click', onClick);
  load();
  return () => { alive = false; tab.root.removeEventListener('click', onClick); };
}

// ---- a program without its own screens ----------------------------------------------------------------------------
function genericOverview(tab) {
  const p = tab.program;
  const id = p.id;
  const dates = p.start_date || p.end_date ? [p.start_date && `from ${fmtDate(p.start_date)}`, p.end_date && `until ${fmtDate(p.end_date)}`].filter(Boolean).join(' ') : '';
  const to = [
    { label: 'Team', note: 'Who works on this program and what they may do', icon: 'shield', to: href(`programs/${id}/team`) },
    { label: 'Partners', note: 'Organizations involved in this program', icon: 'folder', to: href(`programs/${id}/partners`) },
    tab.pcan(['programs.update', 'programs.activate']) && { label: 'Settings', note: 'Name, dates and status', icon: 'sliders', to: href(`programs/${id}/settings`) },
  ].filter(Boolean);
  tab.root.innerHTML = html`<div class="stack-lg">
    <section class="card"><div class="card-head"><h3>About this program</h3>${statusChip(p.status)}</div>
      <dl class="kv"><dt>Kind</dt><dd>${p.category_label}</dd>
        <dt>Description</dt><dd>${p.description || html`<span class="muted">No description yet.</span>`}</dd>
        <dt>Dates</dt><dd>${dates || html`<span class="muted">Not set</span>`}</dd></dl></section>
    <div class="banner info">${icon('info')}<div class="grow">This kind of program does not have its own screens yet. It already has a team, partners and settings, so you can set it up now and add records later.</div></div>
    <div class="tile-grid">${to.map((t) => html`<a class="tile" href="${t.to}"><span class="row-link">${icon(t.icon)}<span class="grow"><span class="t">${t.label}</span></span>${icon('chevron', 'chev')}</span><span class="d muted">${t.note}</span></a>`)}</div></div>`.toString();
}
