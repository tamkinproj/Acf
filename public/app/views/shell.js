import { assetUrl } from '../core/config.js';
import { currentRoute, href, navigate, prefetchViews, routes, visibleRoutes } from '../core/router.js';
import { html, raw, $, $$, esc, fmtTime, plural } from '../core/util.js';
import { icon, mark } from '../core/icons.js';
import { session, can } from '../auth/session.js';
import { describe, syncState, syncNow } from '../sync/engine.js';
import { sheet, toast } from '../core/ui.js';
import { live, db } from '../core/db.js';

// The signed-in frame: sidebar on wide screens, top bar + bottom tabs on phones, and the always-visible sync indicator.

const brandMark = (b) => (b?.logo_hash ? html`<img class="mark-img" src="${assetUrl('assets/logo')}?v=${b.logo_hash}" alt="">` : mark());

export function mountShell(container) {
  const s = session.get();
  const name = s.foundation?.short_name || s.foundation?.name || s.branding?.short_name || s.branding?.name || 'Foundation';
  const items = visibleRoutes();
  const main = items.filter((r) => r.nav === 'main');
  const manage = items.filter((r) => r.nav === 'manage');
  const tabs = [...main.slice(0, 3)];
  const more = items.filter((r) => !tabs.includes(r));

  container.innerHTML = html`
    <div class="frame">
      <aside class="rail" aria-label="Main navigation">
        <a class="rail-brand" href="${href(items[0]?.path ?? 'account')}">${brandMark(s.foundation)}<span><b>${name}</b><small>Foundation</small></span></a>
        <ul class="nav">${main.map((r) => navItem(r))}</ul>
        ${manage.length ? html`<div class="nav-label">Manage</div><ul class="nav">${manage.map((r) => navItem(r))}</ul>` : ''}
        <div class="nav-label">You</div><ul class="nav">${navItem(routes.find((r) => r.path === 'account'))}</ul>
        <div class="rail-foot"><div class="rail-device" data-device></div></div>
      </aside>
      <div class="main">
        <header class="topbar"><span class="brand-sm">${brandMark(s.foundation)}</span><h1 data-title>${name}</h1><button class="sync" type="button" data-sync-chip data-state="ok"><span class="sync-dot"></span><span class="sync-text">Synced</span></button></header>
        <div data-banner></div>
        <main id="view" tabindex="-1"></main>
      </div>
      <nav class="tabbar" aria-label="Main navigation">
        ${tabs.map((r) => html`<a href="${href(r.path)}" data-nav="${r.path}">${icon(r.icon)}<span>${r.title}</span>${r.path === 'sync' ? html`<span class="badge" data-badge hidden></span>` : ''}</a>`)}
        <button type="button" data-more>${icon('more')}<span>More</span></button>
      </nav>
    </div>`.toString();

  const chip = $('[data-sync-chip]', container);
  const view = $('#view', container);
  let unmountView = null;
  let mountToken = 0;

  chip.addEventListener('click', () => navigate('sync'));
  $('[data-more]', container).addEventListener('click', () => openMore(more));

  // ---- live indicator ---------------------------------------------------------------------------------------
  const paint = () => {
    const st = syncState.get();
    const d = describe(st);
    chip.dataset.state = d.state;
    $('.sync-text', chip).textContent = d.text;
    chip.title = st.lastSyncAt ? `Last synced ${fmtTime(st.lastSyncAt)}` : 'Not synced yet';
    const attention = st.pending + st.failed + st.conflicts;
    $$('[data-badge]', container).forEach((b) => { b.hidden = !attention; b.textContent = attention; });
    $$('.nav [data-nav="sync"]', container).forEach((a) => { let b = $('.badge', a); if (attention) { if (!b) { b = Object.assign(document.createElement('span'), { className: 'badge' }); a.appendChild(b); } b.textContent = attention; } else b?.remove(); });
    $('[data-banner]', container).innerHTML = banners(st).toString();
  };
  const offStore = syncState.subscribe(paint);
  const offSession = session.subscribe(paint);
  paint();

  const stopDevice = live(() => db.meta.get('deviceInfo'), (r) => { $('[data-device]', container).innerHTML = r?.value ? html`This device<br><span class="mono">${r.value.device_code}</span>`.toString() : ''; });

  container.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    if (btn.dataset.action === 'sync-now') syncNow();
    if (btn.dataset.action === 'goto-sync') navigate('sync');
  });

  // ---- routing ------------------------------------------------------------------------------------------------
  async function show() {
    const token = ++mountToken;
    const { route, params, redirect } = currentRoute();
    if (redirect && route) { history.replaceState(null, '', href(route.path)); }
    $$('[data-nav]', container).forEach((a) => { if (a.dataset.nav === route.path) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
    $('[data-title]', container).textContent = route.title;
    $('[data-more]', container).classList.toggle('on', more.includes(route));
    document.title = `${route.title} · ${name}`;
    unmountView?.(); unmountView = null;
    view.replaceChildren();
    const el = Object.assign(document.createElement('div'), { className: 'page' });
    view.appendChild(el);
    try {
      const mod = (await route.load()).default;
      if (token !== mountToken) return;
      unmountView = await mod.mount({ root: el, params, can, session, route });
    } catch (e) {
      console.error(e);
      el.innerHTML = html`<div class="empty">${icon('alert')}<b>This screen could not be opened</b><span>${navigator.onLine === false ? 'You are offline and this screen was not loaded earlier. Reconnect once and it will be available.' : 'Reload the page and try again.'}</span></div>`.toString();
    }
    if (!redirect) view.focus({ preventScroll: true });
  }
  const onHash = () => show();
  addEventListener('hashchange', onHash);
  show();
  setTimeout(prefetchViews, 1500);

  return () => { removeEventListener('hashchange', onHash); offStore(); offSession(); stopDevice(); unmountView?.(); };
}

function navItem(r) {
  return html`<li><a href="${href(r.path)}" data-nav="${r.path}">${icon(r.icon)}<span>${r.title}</span></a></li>`;
}

function banners(st) {
  const out = [];
  if (st.phase === 'offline') out.push(html`<div class="banner warn page-banner">${icon('offline')}<div class="grow"><b>You are offline.</b> Everything you do is saved on this device and will sync when the connection returns.</div></div>`);
  if (st.phase === 'auth') out.push(html`<div class="banner bad page-banner">${icon('lock')}<div class="grow"><b>Your session has ended.</b> Your work is safe on this device. Sign in again to sync it.</div><a class="btn sm" href="${href('account')}">Sign in</a></div>`);
  if (st.phase === 'device') out.push(html`<div class="banner bad page-banner">${icon('phone')}<div class="grow"><b>This device is not registered, or was revoked.</b> Your work is safe here. An administrator can register it again.</div><a class="btn sm" href="${href('account')}">Fix</a></div>`);
  if (st.phase === 'error') out.push(html`<div class="banner bad page-banner">${icon('alert')}<div class="grow"><b>Sync failed.</b> ${st.lastError ?? ''} Your changes are still saved here.</div><button class="btn sm" data-action="sync-now" type="button">Retry</button></div>`);
  return out;
}

function openMore(items) {
  sheet({
    title: 'More',
    body: html`<ul class="list">${items.map((r) => html`<li><a class="grow t cursor-link" href="${href(r.path)}" data-close>${icon(r.icon)} ${r.title}</a></li>`)}</ul>`,
  });
}
