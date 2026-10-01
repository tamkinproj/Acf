// Where the app lives. The server writes the base URL into <html data-base>, so the same files work from a domain
// root (https://example.org) or a subfolder (https://example.org/acr) without any configuration.
export const BASE = (globalThis.document?.documentElement?.dataset.base ?? '').replace(/\/$/, '');
export const apiUrl = (path) => `${BASE}/api${path}`;
export const assetUrl = (path) => `${BASE}/${path.replace(/^\//, '')}`;
export const APP_VERSION = '2.0.0';
