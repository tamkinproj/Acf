import * as api from '../../core/api.js';
import { canIn, refreshProfile, session } from '../../auth/session.js';
import { icon } from '../../core/icons.js';
import { href, navigate } from '../../core/router.js';
import { busy, toast } from '../../core/ui.js';
import { $, html, raw } from '../../core/util.js';
import { statusChip } from '../programs.js';

// The frame around one program: its name and state, its tabs, and the tab that is open.
//
// A tab is a module in this folder that exports `default { mount(tab) }` and returns an optional cleanup function. `tab` is:
//   root            the element to draw into (replace its content freely)
//   params          the URL segments after the tab name  (#/programs/<id>/children/<recordId>  ->  ['<recordId>'])
//   program         the program as the API returns it (id, name, category, module, status, config, can: {update, configure, activate})
//   pcan(perm)      may THIS person do `perm` inside THIS program (their foundation role plus their role in the program)
//   go(sub)         open #/programs/<id>/<thisTab>/<sub>          (no argument: the tab itself)
//   link(sub)       the matching href string
//   reload()        re-read the program (after changing its settings or status) and redraw the header
//   setTitle(text)  name the page in the top bar
const AYTAM_TABS = [
  { key: 'overview', label: 'Overview', icon: 'home', perm: 'aytam.view', load: () => import('./overview.js') },
  { key: 'children', label: 'Children', icon: 'heart', perm: 'aytam.view', load: () => import('./children.js') },
  { key: 'families', label: 'Families', icon: 'users', perm: 'aytam.view_all', load: () => import('./families.js') },
  { key: 'guardians', label: 'Guardians', icon: 'user', perm: 'aytam.view_all', load: () => import('./guardians.js') },
  { key: 'registration', label: 'Registration', icon: 'list', perm: ['forms.view', 'aytam.review'], load: () => import('./registration.js') },
  { key: 'import', label: 'Import', icon: 'upload', perm: 'aytam.import', load: () => import('./import.js') },
  { key: 'partners', label: 'Partners', icon: 'folder', perm: null, load: () => import('./partners.js') },
  { key: 'team', label: 'Team', icon: 'shield', perm: null, load: () => import('./team.js') },
  { key: 'settings', label: 'Settings', icon: 'sliders', perm: ['programs.update', 'programs.activate', 'aytam.configure'], load: () => import('./settings.js') },
];
// A program of a kind that has no module yet is still a real program: it has a team, partners and settings.
const GENERIC_TABS = [
  { key: 'overview', label: 'Overview', icon: 'home', perm: null, load: () => import('./overview.js') },
  { key: 'partners', label: 'Partners', icon: 'folder', perm: null, load: () => import('./partners.js') },
  { key: 'team', label: 'Team', icon: 'shield', perm: null, load: () => import('./team.js') },
  { key: 'settings', label: 'Settings', icon: 'sliders', perm: ['programs.update', 'programs.activate'], load: () => import('./settings.js') },
];

export default {
  async mount(ctx) {
    const [id, requested = 'overview', ...rest] = ctx.params;
    let program;
    let cleanup = null;
    let token = 0;

    const load = async () => {
      await refreshProfile();                                  // roles may have changed since sign-in
      program = (await api.get(`/programs/${id}`)).data;
    };
    try {
      await load();
    } catch (e) {
      ctx.root.innerHTML = html`<div class="page-head"><div><div class="crumb"><a href="${href('programs')}">Programs</a></div><h1>Program</h1></div></div>
        <div class="card"><div class="empty">${icon('alert')}<b>${e.status === 404 ? 'Program not found' : 'The program could not be opened'}</b><span>${e.status === 404 ? 'It may have been removed, or you may not have access to it.' : api.explain(e)}</span><a class="btn secondary" href="${href('programs')}">Back to programs</a></div></div>`.toString();
      return;
    }

    const pcan = (perm) => [].concat(perm ?? []).length === 0 || [].concat(perm).some((p) => canIn(id, p) || ctx.can(p));
    const tabs = () => (program.module === 'aytam' ? AYTAM_TABS : GENERIC_TABS).filter((t) => pcan(t.perm));

    const shell = () => {
      const open = tabs().find((t) => t.key === requested) ?? tabs()[0];
      ctx.root.innerHTML = html`
        <div><div class="crumb"><a href="${href('programs')}">Programs</a>${icon('chevron')}<span>${program.category_label}</span></div>
          <div class="page-head"><div><h1>${program.name}</h1><div class="prop">${statusChip(program.status)}${program.description ? html`<span>${program.description}</span>` : ''}</div></div></div></div>
        ${banner()}
        <nav class="tabs-bar" aria-label="Program sections">${tabs().map((t) => html`<a href="${href(`programs/${id}/${t.key}`)}" ${open?.key === t.key ? raw('aria-current="page"') : ''}>${icon(t.icon)}${t.label}</a>`)}</nav>
        <section data-tab aria-live="polite"></section>`.toString();
      return open;
    };

    const banner = () => {
      if (program.status === 'active') return '';
      const tone = program.status === 'archived' ? 'grey' : 'warn';
      const text = { draft: 'This program is a draft. Activate it to start working in it.', inactive: 'This program is inactive. You can look around, but changes are paused.', archived: 'This program is archived. It is kept for the record and can no longer be changed.' }[program.status];
      const next = program.transitions?.includes('active') ? 'active' : null;
      return html`<div class="banner ${tone}">${icon('info')}<div class="grow">${text}</div>${next && program.can.activate ? html`<button class="btn sm" type="button" data-activate>Activate</button>` : ''}</div>`;
    };

    const drawTab = async () => {
      const mine = ++token;
      cleanup?.(); cleanup = null;
      const open = shell();
      ctx.setTitle(program.name);
      const el = $('[data-tab]', ctx.root);
      if (!open) { el.innerHTML = html`<div class="card"><div class="empty">${icon('lock')}<b>Nothing to show here</b><span>You do not have access to any part of this program yet.</span></div></div>`.toString(); return; }
      try {
        const mod = (await open.load()).default;
        if (mine !== token) return;
        const base = `programs/${id}/${open.key}`;
        cleanup = await mod.mount({
          root: el, params: open.key === requested ? rest : [], program, pcan, can: ctx.can, session, setTitle: ctx.setTitle,
          link: (sub = '') => href(sub ? `${base}/${sub}` : base), go: (sub = '') => navigate(sub ? `${base}/${sub}` : base),
          reload: async () => { await load(); await drawTab(); },
        });
      } catch (e) {
        console.error(e);
        if (mine === token) el.innerHTML = html`<div class="card"><div class="empty">${icon('alert')}<b>This section could not be opened</b><span>${navigator.onLine === false ? 'You appear to be offline.' : 'Reload the page and try again.'}</span></div></div>`.toString();
      }
    };

    ctx.root.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-activate]');
      if (!btn) return;
      busy(btn, true, 'Activating…');
      try { await api.post(`/programs/${id}/status`, { status: 'active' }); toast('Program activated.'); await load(); await drawTab(); } catch (err) { toast(api.explain(err), 'bad'); busy(btn, false); }
    });

    await drawTab();
    return () => { token++; cleanup?.(); };
  },
};
