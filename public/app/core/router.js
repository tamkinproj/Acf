import { can } from '../auth/session.js';

// Hash routing: #/places, #/users ... Works the same from a domain root or a subfolder, needs no server rewrite rules,
// and every screen is its own small module that is loaded on demand (and prefetched, so a dropped connection mid-session
// cannot leave a screen unloadable).
export const routes = [
  { path: 'dashboard', title: 'Home', icon: 'home', nav: 'main', perm: 'dashboard.view', load: () => import('../views/dashboard.js') },
  { path: 'places', title: 'Places', icon: 'pin', nav: 'main', perm: 'locations.view', load: () => import('../views/places.js') },
  { path: 'sync', title: 'Sync', icon: 'sync', nav: 'main', perm: null, load: () => import('../views/sync.js') },
  { path: 'foundation', title: 'Foundation', icon: 'building', nav: 'manage', perm: 'foundation.view', load: () => import('../views/foundation.js') },
  { path: 'users', title: 'People', icon: 'users', nav: 'manage', perm: 'users.view', load: () => import('../views/users.js') },
  { path: 'roles', title: 'Roles', icon: 'shield', nav: 'manage', perm: 'roles.view', load: () => import('../views/roles.js') },
  { path: 'devices', title: 'Devices', icon: 'phone', nav: 'manage', perm: 'devices.view', load: () => import('../views/devices.js') },
  { path: 'activity', title: 'Activity', icon: 'list', nav: 'manage', perm: 'audit.view', load: () => import('../views/activity.js') },
  { path: 'settings', title: 'Settings', icon: 'sliders', nav: 'manage', perm: 'settings.view', load: () => import('../views/settings.js') },
  { path: 'account', title: 'My account', icon: 'user', nav: 'me', perm: null, load: () => import('../views/account.js') },
];

export const visibleRoutes = () => routes.filter((r) => can(r.perm));
export const homeRoute = () => visibleRoutes()[0]?.path ?? 'account';

export function currentRoute() {
  const [path, ...rest] = (location.hash.replace(/^#\/?/, '') || '').split('/');
  const route = routes.find((r) => r.path === path);
  return route && can(route.perm) ? { route, params: rest } : { route: routes.find((r) => r.path === homeRoute()), params: [], redirect: true };
}
export const navigate = (path) => { location.hash = `#/${path}`; };
export const href = (path) => `#/${path}`;

/** Fetch every screen's code now, while the connection is good. */
export function prefetchViews() { return Promise.allSettled(visibleRoutes().map((r) => r.load())); }
