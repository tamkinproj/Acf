import { db } from '../core/db.js';
import { icon } from '../core/icons.js';
import { href } from '../core/router.js';
import { ago, fmtDateTime, html, plural } from '../core/util.js';
import { describe, syncNow, syncState } from '../sync/engine.js';
import { kit } from './kit.js';

// Home: a calm summary of where things stand, what happened recently, and the few things you are likely to do next.
export default {
  async mount(ctx) {
    const k = kit(ctx);
    const can = ctx.can;
    let data = { places: 0, people: 0, devices: 0, today: 0, recent: [], queue: [], foundation: null, users: new Map() };
    const draw = () => k.render(view(data, ctx));

    k.live(async () => {
      const today = new Date(); today.setHours(0, 0, 0, 0);
      const alive = (r) => !r.deleted_at;
      const [places, users, devices, audits, queue, foundation] = await Promise.all([
        db.locations.filter(alive).count(), db.users.filter((u) => alive(u) && u.status === 'active').count(), db.devices.filter(alive).count(),
        db.audit_logs.orderBy('occurred_at').reverse().limit(200).toArray(), db.outbox.orderBy('seq').reverse().limit(6).toArray(), db.foundations.toCollection().first(),
      ]);
      return { places, people: users, devices, today: audits.filter((a) => new Date(a.occurred_at) >= today).length, recent: audits.slice(0, 6), queue, foundation };
    }, (d) => { data = { ...data, ...d }; draw(); });

    const off = syncState.subscribe(draw);
    ctx.root.addEventListener('click', (e) => { if (e.target.closest('[data-sync]')) syncNow(); });
    draw();
    return () => { off(); k.cleanup(); };
  },
};

function heroCopy(st) {
  if (st.phase === 'offline') return { tone: 'grey', head: 'Working offline', body: st.pending ? `${plural(st.pending, 'change')} saved on this device, waiting for a connection.` : 'Everything you do is saved on this device and will sync when you are back online.' };
  if (st.phase === 'auth') return { tone: 'red', head: 'Sign in to keep syncing', body: 'Your work is safe on this device. It will sync as soon as you sign in again.' };
  if (st.conflicts) return { tone: 'orange', head: 'A change needs your decision', body: `${plural(st.conflicts, 'record')} edited in two places at once. Nothing was overwritten.` };
  if (st.pending || st.failed) return { tone: 'orange', head: 'Changes on their way', body: `${plural(st.pending + st.failed, 'change')} not yet on the server.` };
  return { tone: 'green', head: 'All saved and in sync', body: st.lastSyncAt ? `Last synced ${ago(st.lastSyncAt)}.` : 'This device is up to date.' };
}

const greeting = () => { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'; };

function view(d, ctx) {
  const st = syncState.get();
  const hero = heroCopy(st);
  const first = (ctx.session.get().user?.name ?? '').split(' ')[0];
  const metrics = [
    ctx.can('locations.view') && { k: 'Places', v: d.places, s: 'recorded', to: 'places' },
    ctx.can('users.view') && { k: 'People', v: d.people, s: 'active accounts', to: 'users' },
    ctx.can('devices.view') && { k: 'Devices', v: d.devices, s: 'registered', to: 'devices' },
    { k: 'Waiting to sync', v: st.pending + st.failed + st.conflicts, s: st.conflicts ? 'includes conflicts' : 'on this device', to: 'sync' },
    ctx.can('audit.view') && { k: 'Activity today', v: d.today, s: 'recorded events', to: 'activity' },
  ].filter(Boolean);
  const actions = [
    ctx.can('locations.manage') && { icon: 'pin', label: 'Add a place', to: 'places' },
    ctx.can('users.manage') && { icon: 'users', label: 'Add a person', to: 'users' },
    st.conflicts > 0 && ctx.can('sync.manage') && { icon: 'alert', label: 'Review sync conflicts', to: 'sync' },
  ].filter(Boolean);

  return html`
    <div class="dash page-head"><div><h1>${first ? `${greeting()}, ${first}` : greeting()}</h1>
      <div class="status-line"><span class="dot ${hero.tone}"></span><span><b>${hero.head}.</b> ${hero.body}</span></div></div>
      <div class="btn-row"><button class="btn secondary" type="button" data-sync>${icon('sync')} Sync now</button></div></div>

    <section><h2>Today's overview</h2>
      <div class="overview">${metrics.map((t) => html`<a class="metric" href="${href(t.to)}"><span class="k">${t.k}</span><span class="v">${t.v}</span><span class="s">${t.s}</span></a>`)}</div></section>

    <div class="two-col">
      <section><h2>${ctx.can('audit.view') ? 'Recent activity' : 'Your recent changes'}</h2>
        <div class="card flush">${ctx.can('audit.view') ? recent(d.recent) : queue(d.queue)}</div>
        <p class="hint see-all"><a href="${href(ctx.can('audit.view') ? 'activity' : 'sync')}">${ctx.can('audit.view') ? 'See all activity' : 'Open the sync center'}</a></p></section>
      ${actions.length ? html`<section><h2>Quick actions</h2><div class="card flush"><ul class="list">${actions.map((a) => html`<li><a class="row-link" href="${href(a.to)}">${icon(a.icon)}<span>${a.label}</span>${icon('chevron', 'chev')}</a></li>`)}</ul></div></section>` : ''}
    </div>`;
}

const recent = (rows) => rows.length ? html`<ul class="list">${rows.map((a) => html`<li><div class="grow"><div class="t">${a.summary}</div><div class="d">${a.user_name ?? 'System'} · ${ago(a.occurred_at)}</div></div></li>`)}</ul>`
  : html`<div class="empty">${icon('list')}<b>No activity yet</b><span>Actions will be recorded here.</span></div>`;
const queue = (rows) => rows.length ? html`<ul class="list">${rows.map((e) => html`<li><span class="pending-dot"></span><div class="grow"><div class="t">${e.op} ${e.entity}</div><div class="d">${e.status}</div></div></li>`)}</ul>`
  : html`<div class="empty">${icon('check')}<b>Nothing waiting</b><span>Everything you did is already on the server.</span></div>`;
