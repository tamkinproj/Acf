import { getMeta, setMeta, delMeta } from '../core/db.js';

// Device PIN: unlocks the local app when the server cannot be asked (offline, or a brief idle lock).
// Only a salted PBKDF2 verifier is stored - never the PIN. After 5 wrong tries the PIN stops working and the person
// must sign in with their password (which needs the server) - a stolen phone cannot be brute-forced offline.

const ITERATIONS = 310000;
const MAX_FAILS = 5;
const enc = new TextEncoder();
const b64 = (buf) => btoa(String.fromCharCode(...new Uint8Array(buf)));
const unb64 = (s) => Uint8Array.from(atob(s), (c) => c.charCodeAt(0));

async function derive(pin, salt, iterations) {
  const key = await crypto.subtle.importKey('raw', enc.encode(pin), 'PBKDF2', false, ['deriveBits']);
  return new Uint8Array(await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt, iterations }, key, 256));
}
const same = (a, b) => { if (a.length !== b.length) return false; let d = 0; for (let i = 0; i < a.length; i++) d |= a[i] ^ b[i]; return d === 0; };

export const validPin = (pin) => /^\d{6}$/.test(pin) && !/^(\d)\1{5}$/.test(pin) && !['123456', '654321', '012345'].includes(pin);

export async function hasPin() { return !!(await getMeta('pin')); }

export async function setPin(pin) {
  if (!validPin(pin)) throw new Error('Use 6 digits that are not all the same or a simple sequence.');
  const salt = crypto.getRandomValues(new Uint8Array(16));
  await setMeta('pin', { salt: b64(salt), hash: b64(await derive(pin, salt, ITERATIONS)), iterations: ITERATIONS, fails: 0 });
}

/** @returns {{ok:boolean, blocked?:boolean, left?:number}} */
export async function verifyPin(pin) {
  const rec = await getMeta('pin');
  if (!rec) return { ok: false, blocked: true, left: 0 };
  if (rec.fails >= MAX_FAILS) return { ok: false, blocked: true, left: 0 };
  const ok = same(await derive(pin, unb64(rec.salt), rec.iterations), unb64(rec.hash));
  if (ok) { if (rec.fails) await setMeta('pin', { ...rec, fails: 0 }); return { ok: true }; }
  const fails = rec.fails + 1;
  await setMeta('pin', { ...rec, fails });
  return { ok: false, blocked: fails >= MAX_FAILS, left: Math.max(0, MAX_FAILS - fails) };
}

/** A successful password sign-in proves identity, so it clears the failure counter. */
export async function resetPinFailures() { const rec = await getMeta('pin'); if (rec?.fails) await setMeta('pin', { ...rec, fails: 0 }); }
export const clearPin = () => delMeta('pin');
export async function pinBlocked() { const rec = await getMeta('pin'); return !!rec && rec.fails >= MAX_FAILS; }
