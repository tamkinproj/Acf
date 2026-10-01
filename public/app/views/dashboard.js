import { PLANNED_MODULES } from '../core/config.js';
import { db } from '../core/db.js';
import { icon, star } from '../core/icons.js';
import { href } from '../core/router.js';
import { ago, fmtDateTime, html, plural } from '../core/util.js';
import { describe, syncNow, syncState } from '../sync/engine.js';
import { kit } from './kit.js';

// Home: where things stand, at a glance - and where the foundation is going next.
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
  if (st.phase === 'offline') return { head: 'Working offline', body: st.pending ? `${plural(st.pending, 'change')} saved on this device and waiting for a connection.` : 'Everything you do is saved on this device and will sync when you are back online.' };
  if (st.phase === 'auth') return { head: 'Sign in to keep syncing', body: 'Your work is safe on this device. It will sync as soon as you sign in again.' };
  if (st.conflicts) return { head: 'A change needs your decision', body: `${plural(st.conflicts, 'record')} edited in two places at once. Nothing was overwritten.` };
  if (st.pending || st.failed) return { head: 'Changes on their way', body: `${plural(st.pending + st.failed, 'change')} not yet on the server.` };
  return { head: 'All saved and in sync', body: st.lastSyncAt ? `Last synced ${ago(st.lastSyncAt)}.` : 'This device is up to date.' };
}

function view(d, ctx) {
  const st = syncState.get();
  const hero = heroCopy(st);
  const name = ctx.session.get().foundation?.name ?? d.foundation?.name ?? '';
  const first = (ctx.session.get().user?.name ?? '').split(' ')[0];
  const tiles = [
    ctx.can('locations.view') && { k: 'Places', v: d.places, s: 'recorded locations', to: 'places' },
    ctx.can('users.view') && { k: 'People', v: d.people, s: 'active accounts', to: 'users' },
    ctx.can('devices.view') && { k: 'Devices', v: d.devices, s: 'registered', to: 'devices' },
    { k: 'Waiting to sync', v: st.pending + st.failed + st.conflicts, s: st.conflicts ? 'includes conflicts' : 'on this device', to: 'sync' },
    ctx.can('audit.view') && { k: 'Activity today', v: d.today, s: 'recorded events', to: 'activity' },
  ].filter(Boolean);

  return html`
    <section class="hero"><div class="kicker">${name}</div>
      <h2>${first ? `Salaam, ${first}.` : 'Welcome.'} ${hero.head}.</h2>
      <p>${hero.body}</p>
      <div class="actions btn-row">
        <button class="btn gold" type="button" data-sync>${icon('sync')} Sync now</button>
        ${ctx.can('locations.manage') ? html`<a class="btn ghost" href="${href('places')}">${icon('pin')} Manage places</a>` : ''}
      </div></section>

    <section class="grid stats">${tiles.map((t) => html`<a class="card stat notch" href="${href(t.to)}"><span class="k">${t.k}</span><span class="v">${t.v}</span><span class="s">${t.s}</span></a>`)}</section>

    <section class="grid cols-2">
      <div class="card"><div class="card-head"><h3>${ctx.can('audit.view') ? 'Recent activity' : 'Your recent changes'}</h3>
        ${ctx.can('audit.view') ? html`<a href="${href('activity')}">See all</a>` : html`<a href="${href('sync')}">Sync center</a>`}</div>
        ${ctx.can('audit.view') ? recent(d.recent) : queue(d.queue)}</div>
      <div class="card"><div class="card-head"><h3>This device</h3></div>
        <dl class="kv"><dt>Status</dt><dd>${describe(st).text}</dd><dt>Last sync</dt><dd>${st.lastSyncAt ? fmtDateTime(st.lastSyncAt) : 'Not yet'}</dd>
        <dt>Storage</dt><dd>Saved in this browser; works without a connection</dd></dl></div>
    </section>

    <section><div class="page-head"><div><h3>Coming next</h3><p>The foundation's core is in place. These areas will be added on top of it, without rebuilding anything.</p></div></div>
      <div class="modules">${PLANNED_MODULES.map((m) => html`<div class="module">${icon(m.icon)}<span class="soon">Soon</span><div><b>${m.name}</b><small>${m.note}</small></div></div>`)}</div></section>`;
}

const recent = (rows) => rows.length ? html`<ul class="list">${rows.map((a) => html`<li><span class="avatar">${star()}</span><div class="grow"><div class="t">${a.summary}</div><div class="d">${a.user_name ?? 'System'} · ${ago(a.occurred_at)}</div></div></li>`)}</ul>`
  : html`<div class="empty">${icon('list')}<b>No activity yet</b><span>Actions will be recorded here.</span></div>`;
const queue = (rows) => rows.length ? html`<ul class="list">${rows.map((e) => html`<li><span class="pending-dot"></span><div class="grow"><div class="t">${e.op} ${e.entity}</div><div class="d">${e.status}</div></div></li>`)}</ul>`
  : html`<div class="empty">${icon('check')}<b>Nothing waiting</b><span>Everything you did is already on the server.</span></div>`;
