import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { searchInput } from '../../core/ui.js';
import { $, ago, debounce, fmtDateTime, html } from '../../core/util.js';

// What the platform itself has done: foundations created or switched, administrators added, platform sign-ins.
// Each foundation's own activity log stays with that foundation.
export default {
  async mount(ctx) {
    let rows = [];
    let meta = { page: 1, last_page: 1, total: 0 };
    let q = '';
    let action = '';
    let page = 1;
    let failed = null;
    let loading = true;
    const draw = () => { ctx.root.innerHTML = view({ rows, meta, q, action, failed, loading }).toString(); };
    const load = async () => {
      loading = true;
      try { const r = await api.get(`/platform/activity?page=${page}&q=${encodeURIComponent(q)}&action=${action}`); rows = r.data; meta = r.meta; failed = null; } catch (e) { failed = api.explain(e); }
      loading = false; draw();
      if (q) { const i = $('#q', ctx.root); i?.focus(); i?.setSelectionRange(q.length, q.length); }
    };
    const search = debounce(() => { page = 1; load(); }, 300);
    ctx.root.addEventListener('input', (e) => { if (e.target.id === 'q') { q = e.target.value; search(); } });
    ctx.root.addEventListener('change', (e) => { if (e.target.id === 'act') { action = e.target.value; page = 1; load(); } });
    ctx.root.addEventListener('click', (e) => {
      if (e.target.closest('[data-retry]')) return load();
      const go = e.target.closest('[data-page]');
      if (go) { page = Number(go.dataset.page); load(); }
    });
    draw();
    await load();
  },
};

function view({ rows, meta, q, action, failed, loading }) {
  return html`
    <div class="page-head"><div><h1>Platform activity</h1><p>A record of what the platform itself has done. It does not include foundations' own activity.</p></div></div>
    ${failed ? html`<div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>` : ''}
    <div class="card flush"><div class="filters"><div class="grow">${searchInput({ value: q, placeholder: 'Search activity', label: 'Search activity' })}</div>
      <div class="field"><label class="sr-only" for="act">Kind</label><select class="select" id="act">${[['', 'Everything'], ['foundation', 'Foundations'], ['auth', 'Sign-ins'], ['user', 'Administrators'], ['system', 'System']].map(([v, t]) => html`<option value="${v}" ${action === v ? 'selected' : ''}>${t}</option>`)}</select></div></div>
      ${loading && !rows.length ? html`<div class="skeleton row"></div><div class="skeleton row"></div>`
        : rows.length ? html`<ul class="list timeline">${rows.map((a) => html`<li><span class="dot grey"></span><div class="grow"><div class="t">${a.summary}</div><div class="d">${a.user_name ?? 'System'} · ${fmtDateTime(a.occurred_at)} · ${ago(a.occurred_at)}</div></div></li>`)}</ul>
          ${meta.last_page > 1 ? html`<div class="btn-row center"><button class="btn secondary sm" type="button" data-page="${meta.page - 1}" ${meta.page <= 1 ? 'disabled' : ''}>Newer</button><span class="muted">Page ${meta.page} of ${meta.last_page}</span><button class="btn secondary sm" type="button" data-page="${meta.page + 1}" ${meta.page >= meta.last_page ? 'disabled' : ''}>Older</button></div>` : ''}`
          : html`<div class="empty">${icon('list')}<b>No activity found</b></div>`}</div>`;
}
