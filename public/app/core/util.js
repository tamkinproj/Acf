// Small, dependency-free helpers shared by the whole client.

/** UUIDv7: 48-bit millisecond timestamp + random. Time-ordered, collision-safe, generated offline on any device. */
export function uuid7() {
  const b = new Uint8Array(16);
  crypto.getRandomValues(b);
  const ms = BigInt(Date.now());
  for (let i = 0; i < 6; i++) b[i] = Number((ms >> BigInt(8 * (5 - i))) & 0xffn);
  b[6] = (b[6] & 0x0f) | 0x70;          // version 7
  b[8] = (b[8] & 0x3f) | 0x80;          // RFC 4122 variant
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

export const isUuid = (v) => typeof v === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(v);

// ---- safe HTML ------------------------------------------------------------------------------------
// Every interpolated value is escaped unless it was produced by html`` itself or marked raw().
// Views therefore cannot be tricked into executing text that came from a database, a user or a server.
class Safe { constructor(s) { this.s = s; } toString() { return this.s; } }
export const raw = (s) => new Safe(String(s));
const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' };
export const esc = (v) => String(v ?? '').replace(/[&<>"'`]/g, (c) => ESC[c]);
const part = (v) => (v instanceof Safe ? v.s : Array.isArray(v) ? v.map(part).join('') : v === null || v === undefined || v === false ? '' : esc(v));
export function html(strings, ...values) {
  let out = strings[0];
  for (let i = 0; i < values.length; i++) out += part(values[i]) + strings[i + 1];
  return new Safe(out);
}

export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
export const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
export const pick = (o, keys) => Object.fromEntries(keys.filter((k) => k in o).map((k) => [k, o[k]]));
export const clone = (v) => (v === undefined ? v : JSON.parse(JSON.stringify(v)));
export const nowIso = () => new Date().toISOString();
export const initials = (name) => (String(name || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0]).join('') || '?').toUpperCase();

// ---- dates (stored/exchanged in UTC; shown in the foundation's chosen display timezone) -------------------
let displayTz;
export const setDisplayTimezone = (tz) => { displayTz = tz; };
function fmt(iso, opts) {
  if (!iso) return '';
  try { return new Intl.DateTimeFormat(undefined, { timeZone: displayTz, ...opts }).format(new Date(iso)); }
  catch { return new Intl.DateTimeFormat(undefined, opts).format(new Date(iso)); }
}
export const fmtDateTime = (iso) => fmt(iso, { dateStyle: 'medium', timeStyle: 'short' });
export const fmtDate = (iso) => fmt(iso, { dateStyle: 'medium' });
export const fmtTime = (iso) => fmt(iso, { timeStyle: 'short' });
export function ago(iso) {
  if (!iso) return 'never';
  const s = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 45) return 'just now';
  if (s < 90) return '1 minute ago';
  if (s < 3600) return `${Math.round(s / 60)} minutes ago`;
  if (s < 5400) return '1 hour ago';
  if (s < 86400) return `${Math.round(s / 3600)} hours ago`;
  return fmtDate(iso);
}
export const plural = (n, one, many = one + 's') => `${n} ${n === 1 ? one : many}`;
