import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { href } from '../../core/router.js';
import { ago, fmtBytes, html, plural } from '../../core/util.js';

// The platform overview. Counts only: the people who run the service do not read a foundation's records.
export default {
  async mount(ctx) {
    const draw = (d, failed) => { ctx.root.innerHTML = view(d, failed, ctx).toString(); };
    ctx.root.addEventListener('click', (e) => { if (e.target.closest('[data-retry]')) load(); });
    async function load() {
      try { draw((await api.get('/platform/dashboard')).data, null); } catch (e) { draw(null, api.explain(e)); }
    }
    await load();
  },
};

function view(d, failed, ctx) {
  if (failed) return html`<div class="page-head"><div><h1>Platform overview</h1></div></div><div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>`;
  const f = d.foundations;
  const ok = d.system.status === 'ok';
  const first = (ctx.session.get().user?.name ?? '').split(' ')[0];
  const metrics = [
    { k: 'Foundations', v: f.active, s: f.total === f.active ? 'all active' : `${f.total} in total`, to: 'foundations' },
    { k: 'People', v: d.totals.active_users, s: 'active accounts', to: 'foundations' },
    { k: 'Programs', v: d.totals.active_programs, s: `${d.totals.programs} in total`, to: 'foundations' },
    { k: 'Organizations', v: d.totals.organizations, s: 'across all foundations', to: 'foundations' },
    { k: 'Stored documents', v: d.storage.documents, s: fmtBytes(d.storage.bytes), to: 'foundations' },
  ];
  return html`
    <div class="page-head"><div><h1>${first ? `Hello, ${first}` : 'Platform overview'}</h1>
      <div class="status-line"><span class="dot ${ok ? 'green' : ''}"></span><span><b>${ok ? 'The service is running normally' : 'The service needs attention'}.</b> ${plural(f.total, 'foundation')}, ${plural(d.totals.users, 'person', 'people')}.</span></div></div>
      ${ctx.can('platform.foundations.manage') ? html`<div class="btn-row"><a class="btn" href="${href('foundations')}">${icon('plus')} Manage foundations</a></div>` : ''}</div>

    <section><h2>At a glance</h2><div class="overview">${metrics.map((t) => html`<a class="metric" href="${href(t.to)}"><span class="k">${t.k}</span><span class="v">${t.v}</span><span class="s">${t.s}</span></a>`)}</div></section>

    ${f.inactive || f.suspended ? html`<div class="banner warn">${icon('alert')}<div class="grow">${f.suspended ? plural(f.suspended, 'foundation') + ' suspended' : ''}${f.suspended && f.inactive ? ' and ' : ''}${f.inactive ? plural(f.inactive, 'foundation') + ' inactive' : ''}. Their people cannot sign in.</div><a class="btn sm" href="${href('foundations')}">Review</a></div>` : ''}

    <div class="two-col">
      <section><h2>Recent platform activity</h2><div class="card flush">${d.recent_activity.length
        ? html`<ul class="list">${d.recent_activity.map((a) => html`<li><div class="grow"><div class="t">${a.summary}</div><div class="d">${a.user_name ?? 'System'} · ${ago(a.occurred_at)}</div></div></li>`)}</ul>`
        : html`<div class="empty">${icon('list')}<b>No activity yet</b><span>Creating foundations and managing administrators is recorded here.</span></div>`}</div>
        <p class="hint see-all"><a href="${href('platform-activity')}">See all activity</a></p></section>
      <section><h2>System</h2><div class="card"><dl class="kv">
        <dt>Status</dt><dd>${ok ? html`<span class="chip green">Healthy</span>` : html`<span class="chip orange">Attention</span>`}</dd>
        <dt>Version</dt><dd>${d.system.version}</dd>
        <dt>Database</dt><dd>${d.system.database}${d.system.pending_migrations ? html` <span class="chip orange">${plural(d.system.pending_migrations, 'update')} pending</span>` : ''}</dd>
        <dt>Storage</dt><dd>${d.system.storage_writable ? 'Writable' : html`<span class="chip red">Not writable</span>`}</dd>
        <dt>Platform admins</dt><dd>${d.totals.platform_admins}</dd></dl></div></section>
    </div>`;
}
