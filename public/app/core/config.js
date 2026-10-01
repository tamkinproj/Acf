// Where the app lives. The server writes the base URL into <html data-base>, so the same files work from a domain
// root (https://example.org) or a subfolder (https://example.org/acr) without any configuration.
export const BASE = (globalThis.document?.documentElement?.dataset.base ?? '').replace(/\/$/, '');
export const apiUrl = (path) => `${BASE}/api${path}`;
export const assetUrl = (path) => `${BASE}/${path.replace(/^\//, '')}`;
export const APP_VERSION = '1.0.0';
export const PLANNED_MODULES = [
  { key: 'aytam', name: 'Aytam', note: 'Orphan care & sponsorship', icon: 'heart' },
  { key: 'beneficiaries', name: 'Beneficiaries', note: 'Families, households, needs', icon: 'users' },
  { key: 'relief', name: 'Relief Goods', note: 'Packages & distribution', icon: 'box' },
  { key: 'donations', name: 'Donations', note: 'Donors, campaigns, receipts', icon: 'coin' },
  { key: 'projects', name: 'Projects', note: 'Budgets, activities, progress', icon: 'flag' },
  { key: 'inventory', name: 'Inventory', note: 'Stock, warehouses, movement', icon: 'shelf' },
  { key: 'volunteers', name: 'Volunteers', note: 'Assignments & attendance', icon: 'hand' },
  { key: 'reports', name: 'Reports', note: 'Insight across everything', icon: 'chart' },
];
