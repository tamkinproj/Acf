import { can, session } from '../auth/session.js';

// Hash routing: #/places, #/programs/<id>/children ... Works the same from a domain root or a subfolder, needs no server rewrite
// rules, and every screen is its own small module that is loaded on demand (and prefetched, so a dropped connection mid-session
// cannot leave a screen unloadable).
//
// Two worlds share one shell: platform administrators (who run the service and never see a foundation's records) and the people
// of a foundation. `kind` keeps a route in its own world; `visible` lets a route depend on more than one permission.
export const routes = [
  // ---- platform administrators ----
  { path: 'platform', kind: 'platform', title: 'Overview', icon: 'home', nav: 'main', perm: 'platform.view', load: () => import('../views/platform/dashboard.js') },
  { path: 'foundations', kind: 'platform', title: 'Foundations', icon: 'building', nav: 'main', perm: 'platform.view', load: () => import('../views/platform/foundations.js') },
  { path: 'platform-users', kind: 'platform', title: 'Administrators', icon: 'shield', nav: 'manage', perm: 'platform.users.manage', load: () => import('../views/platform/users.js') },
  { path: 'platform-activity', kind: 'platform', title: 'Activity', icon: 'list', nav: 'manage', perm: 'platform.view', load: () => import('../views/platform/activity.js') },
  { path: 'platform-settings', kind: 'platform', title: 'Settings', icon: 'sliders', nav: 'manage', perm: 'platform.view', load: () => import('../views/platform/settings.js') },

  // ---- the people of a foundation ----
  { path: 'dashboard', kind: 'foundation', title: 'Home', icon: 'home', nav: 'main', perm: 'dashboard.view', load: () => import('../views/dashboard.js') },
  { path: 'programs', kind: 'foundation', title: 'Programs', icon: 'heart', nav: 'main', visible: () => can('programs.view') || session.get().programs.length > 0, load: () => import('../views/programs.js') },
  { path: 'sync', kind: 'foundation', title: 'Sync', icon: 'sync', nav: 'main', perm: null, load: () => import('../views/sync.js') },
  { path: 'organizations', kind: 'foundation', title: 'Organizations', icon: 'folder', nav: 'manage', perm: 'organizations.view', load: () => import('../views/organizations.js') },
  { path: 'places', kind: 'foundation', title: 'Places', icon: 'pin', nav: 'manage', perm: 'locations.view', load: () => import('../views/places.js') },
  { path: 'foundation', kind: 'foundation', title: 'Foundation', icon: 'building', nav: 'manage', perm: 'foundation.view', load: () => import('../views/foundation.js') },
  { path: 'users', kind: 'foundation', title: 'People', icon: 'users', nav: 'manage', perm: 'users.view', load: () => import('../views/users.js') },
  { path: 'roles', kind: 'foundation', title: 'Roles', icon: 'shield', nav: 'manage', perm: 'roles.view', load: () => import('../views/roles.js') },
  { path: 'devices', kind: 'foundation', title: 'Devices', icon: 'phone', nav: 'manage', perm: 'devices.view', load: () => import('../views/devices.js') },
  { path: 'activity', kind: 'foundation', title: 'Activity', icon: 'list', nav: 'manage', perm: 'audit.view', load: () => import('../views/activity.js') },
  { path: 'settings', kind: 'foundation', title: 'Settings', icon: 'sliders', nav: 'manage', perm: 'settings.view', load: () => import('../views/settings.js') },

  { path: 'account', kind: 'both', title: 'My account', icon: 'user', nav: 'me', perm: null, load: () => import('../views/account.js') },
];

const allowed = (r) => (r.kind === 'both' || r.kind === session.get().kind) && (r.visible ? r.visible() : can(r.perm));
export const visibleRoutes = () => routes.filter(allowed);
export const homeRoute = () => visibleRoutes()[0]?.path ?? 'account';

export function currentRoute() {
  const [path, ...rest] = (location.hash.replace(/^#\/?/, '') || '').split('/');
  const route = routes.find((r) => r.path === path);
  return route && allowed(route) ? { route, params: rest } : { route: routes.find((r) => r.path === homeRoute()), params: [], redirect: true };
}
export const navigate = (path) => { location.hash = `#/${path}`; };
export const href = (path) => `#/${path}`;

/** Fetch every screen's code now, while the connection is good. */
export function prefetchViews() { return Promise.allSettled(visibleRoutes().map((r) => r.load())); }
