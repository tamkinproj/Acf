import * as api from '../core/api.js';
import { db, openDb, getMeta, setMeta, delMeta, wipeLocalData } from '../core/db.js';
import { createStore } from '../core/store.js';
import { setDisplayTimezone } from '../core/util.js';
import * as engine from '../sync/engine.js';
import { unsyncedCount } from '../sync/outbox.js';
import { clearPin, hasPin, resetPinFailures } from './pin.js';

// Who is using this browser, and what they may do. The state machine behind the first screen the person sees.
//
//   booting       checking the server and the local database
//   anon          nobody signed in -> login screen
//   needs-device  signed in, but this browser is not a registered device yet -> onboarding
//   locked        signed in earlier; asks for the PIN (or the password) before showing data
//   active        working

export const session = createStore({
  status: 'booting', user: null, permissions: [], foundation: null, branding: null,
  online: true, hasPin: false, conflictUser: null, mustChangePassword: false, idleMinutes: 15,
});

export const can = (perm) => !perm || session.get().permissions.includes(perm);

async function loadBranding() {
  try {
    const { data } = await api.get('/system/status', { timeout: 6000 });
    await setMeta('branding', data);
    return data;
  } catch { return getMeta('branding'); }
}

async function applyProfile(data) {
  const profile = { user: data.user, permissions: data.permissions, foundation: data.foundation, idleMinutes: data.session?.idle_lock_minutes ?? 15 };
  await setMeta('profile', profile);
  session.set({ user: data.user, permissions: data.permissions, foundation: data.foundation, idleMinutes: profile.idleMinutes, mustChangePassword: !!data.user.must_change_password });
}

async function loadDisplayTimezone() {
  const row = await db.settings.where('key').equals('app.timezone').first().catch(() => null);
  if (row?.value) setDisplayTimezone(row.value);
}

/** First thing the app does. Decides which screen to show. */
export async function boot() {
  await openDb();
  api.setDeviceToken(await getMeta('deviceToken'));
  const branding = await loadBranding();
  session.set({ branding, hasPin: await hasPin() });

  const cached = await getMeta('profile');
  try {
    const { data } = await api.get('/auth/me', { timeout: 7000 });
    await handleSignedIn(data);
  } catch (e) {
    if (e.network && cached) {
      // Server unreachable but this device has been used before: work from the local copy.
      session.set({ user: cached.user, permissions: cached.permissions, foundation: cached.foundation, idleMinutes: cached.idleMinutes, online: false });
      await loadDisplayTimezone();
      session.set({ status: (await hasPin()) ? 'locked' : 'active' });
      if (!(await hasPin())) engine.start();
      return;
    }
    session.set({ status: 'anon', online: !e.network });
  }
}

async function handleSignedIn(data) {
  const previous = (await getMeta('profile'))?.user;
  if (previous && previous.id !== data.user.id) {
    // A different person is signing in on a device that holds someone else's data. Their permissions differ, so the
    // local copy must not carry over - but never destroy work that has not reached the server.
    const waiting = await unsyncedCount();
    if (waiting) {
      session.set({ status: 'anon', conflictUser: { name: previous.name, email: previous.email, waiting } });
      await api.post('/auth/logout').catch(() => {});
      return false;
    }
    engine.stop();
    await wipeLocalData();
    await clearPin();
    session.set({ hasPin: false });
  }
  await applyProfile(data);
  session.set({ conflictUser: null, online: true });
  await loadDisplayTimezone();
  if (data.user.must_change_password) { session.set({ status: 'active' }); return true; }
  if (!api.hasDeviceToken()) { session.set({ status: 'needs-device' }); return true; }
  await resetPinFailures();
  session.set({ status: 'active' });
  engine.start();
  return true;
}

export async function login(email, password) {
  const { data } = await api.post('/auth/login', { email, password });
  return handleSignedIn(data);
}

/** Password re-entry for the same person (idle lock while online, or after the server session expired). */
export async function reauthenticate(password) {
  const user = session.get().user;
  const { data } = await api.post('/auth/login', { email: user.email, password });
  if (data.user.id !== user.id) throw new Error('That account is different from the one on this device.');
  await applyProfile(data);
  await resetPinFailures();
  session.set({ status: 'active', online: true });
  engine.start();
  engine.kick(0);
}

export async function registerDevice(token) {
  await setMeta('deviceToken', token);
  api.setDeviceToken(token);
  session.set({ status: 'active' });
  engine.start();
  engine.kick(0);
}

export async function forgetDevice() { await delMeta('deviceToken'); api.setDeviceToken(null); }

export function lock() {
  if (session.get().status === 'active') session.set({ status: 'locked' });
}
export function unlock() { session.set({ status: 'active' }); engine.kick(300); }

/** Sign out. Returns { ok:false, waiting } if there is unsynced work and the caller has not confirmed discarding it. */
export async function logout({ discard = false } = {}) {
  engine.stop();
  try { await engine.syncNow(); } catch { /* offline: the check below decides */ }
  const waiting = await unsyncedCount();
  if (waiting && !discard) { engine.start(); return { ok: false, waiting }; }
  await api.post('/auth/logout').catch(() => {});
  await wipeLocalData();
  session.set({ status: 'anon', user: null, permissions: [], foundation: null, hasPin: false, conflictUser: null });
  return { ok: true };
}

/** The server session ended while the app was open: keep the data, ask for the password. */
export function sessionExpired() {
  if (session.get().status === 'active') session.set({ online: true });
}

export async function refreshProfile() {
  try { const { data } = await api.get('/auth/me'); await applyProfile(data); } catch { /* keep the cached profile */ }
}
