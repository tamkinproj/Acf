import { apiUrl, APP_VERSION } from './config.js';

// Thin fetch wrapper for the JSON API.
//  - same-origin cookie session + CSRF: reads the XSRF-TOKEN cookie, retries once on 419
//  - sends the device token (Authorization: Bearer) when this browser has been registered as a device
//  - a lost connection throws NetworkError (never a generic TypeError), so callers can queue work instead of failing
//  - everything has a timeout: a half-dead mobile connection must not hang the UI

export class NetworkError extends Error { constructor(msg = 'No connection to the server') { super(msg); this.name = 'NetworkError'; this.network = true; } }
export class ApiError extends Error {
  constructor(res) { super(res.message || `Request failed (${res.status})`); this.name = 'ApiError'; Object.assign(this, { status: res.status, code: res.code, errors: res.errors, data: res.data }); }
}

let deviceToken = null;
export const setDeviceToken = (t) => { deviceToken = t || null; };
export const hasDeviceToken = () => !!deviceToken;

const cookie = (name) => {
  const m = document.cookie.split('; ').find((c) => c.startsWith(name + '='));
  return m ? decodeURIComponent(m.slice(name.length + 1)) : null;
};
let csrfReady = null;
const ensureCsrf = (force = false) => {
  if (force || !csrfReady || !cookie('XSRF-TOKEN')) {
    csrfReady = fetch(apiUrl('/auth/csrf'), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(() => {}).then(() => {});
  }
  return csrfReady;
};

async function send(method, path, body, { timeout = 15000, signal } = {}) {
  const writing = method !== 'GET';
  if (writing) await ensureCsrf();
  const headers = { Accept: 'application/json', 'X-App-Version': APP_VERSION };
  if (deviceToken) headers.Authorization = `Bearer ${deviceToken}`;
  const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';
  const xsrf = cookie('XSRF-TOKEN');
  if (writing && xsrf) headers['X-XSRF-TOKEN'] = xsrf;

  const ctl = new AbortController();
  const timer = setTimeout(() => ctl.abort(), timeout);
  signal?.addEventListener('abort', () => ctl.abort());
  let res;
  try {
    res = await fetch(apiUrl(path), { method, headers, credentials: 'same-origin', signal: ctl.signal, body: body === undefined ? undefined : isForm ? body : JSON.stringify(body) });
  } catch {
    throw new NetworkError();
  } finally {
    clearTimeout(timer);
  }
  let json = null;
  try { json = await res.json(); } catch { /* non-JSON (e.g. a host error page) */ }
  return { status: res.status, ok: res.ok, json, retryAfter: res.headers.get('Retry-After') };
}

let onSignedOut = null;
/** Called when the server says the sign-in is gone (session ended or revoked) on a request that needed one. */
export const setSignedOutHandler = (fn) => { onSignedOut = fn; };

/** Returns data on success; throws ApiError (server said no) or NetworkError (couldn't reach it). */
export async function call(method, path, body, opts) {
  let r = await send(method, path, body, opts);
  if (r.status === 419) { await ensureCsrf(true); r = await send(method, path, body, opts); }   // CSRF token went stale
  if (r.status >= 500 && !r.json) throw new NetworkError('The server is having trouble. Try again shortly.');
  if (r.status === 401 && r.json?.code === 'UNAUTHENTICATED' && !path.startsWith('/auth/')) onSignedOut?.();
  if (!r.ok) throw new ApiError({ status: r.status, code: r.json?.code, message: r.json?.message, errors: r.json?.errors, data: r.json?.data });
  return r.json ? { data: r.json.data, meta: r.json.meta } : { data: null };
}
export const get = (p, o) => call('GET', p, undefined, o);
export const post = (p, b = {}, o) => call('POST', p, b, o);
export const put = (p, b = {}, o) => call('PUT', p, b, o);
export const patch = (p, b = {}, o) => call('PATCH', p, b, o);
export const del = (p, o) => call('DELETE', p, undefined, o);

/** Friendly one-line message for any error. */
export function explain(e) {
  if (e?.network) return 'You are offline, or the server cannot be reached.';
  if (e?.errors) { const first = Object.values(e.errors).flat()[0]; if (first) return String(first); }
  return e?.message || 'Something went wrong.';
}
