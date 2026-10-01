import { db, table, getMeta, live } from '../core/db.js';
import * as api from '../core/api.js';
import { createStore } from '../core/store.js';
import { entities } from '../core/entities.js';
import { plural } from '../core/util.js';
import { protectedFields, UNSYNCED } from './outbox.js';

// The synchronization engine (browser side of /api/sync/*).
//
//   push: deliver the outbox IN ORDER; every change carries its own idempotency key, so resending is always safe
//   pull: fetch everything after our cursor; apply it, and advance the cursor, in ONE local transaction
//   never lose work: network errors, expired sessions and server hiccups leave the outbox untouched
//
// Phases:  idle (synced) | syncing | offline | auth (sign in again) | device (device token missing/revoked) | error (will retry)

export const syncState = createStore({
  phase: 'idle', pending: 0, failed: 0, conflicts: 0, lastSyncAt: null, lastError: null, errorCode: null,
  progress: null, bootstrapping: false, serverOpenConflicts: 0,
});

const online = () => globalThis.navigator?.onLine !== false;
let running = null;
let kickTimer = null;
let loopTimer = null;
let failures = 0;
let started = false;
let stopLive = null;
let listeners = [];
let notify = () => {};
export const onSyncNotice = (fn) => { notify = fn; };

/** One-line description for the sync indicator. Priority matches what a person most needs to know. */
export function describe(s = syncState.get()) {
  const waiting = s.pending;
  if (s.phase === 'syncing') return { state: 'busy', text: s.bootstrapping ? 'Preparing this device…' : 'Syncing…' };
  if (s.phase === 'auth') return { state: 'auth', text: 'Sign in to sync' };
  if (s.phase === 'device') return { state: 'auth', text: 'Register this device' };
  if (s.phase === 'offline') return { state: 'offline', text: waiting ? `Offline · ${plural(waiting, 'change')} waiting` : 'Offline' };
  if (s.phase === 'error') return { state: 'failed', text: 'Sync failed — retry' };
  if (s.conflicts) return { state: 'conflict', text: `${plural(s.conflicts, 'conflict')} to review` };
  if (s.failed) return { state: 'failed', text: `${plural(s.failed, 'change')} need attention` };
  if (waiting) return { state: 'pending', text: `${plural(waiting, 'change')} waiting to sync` };
  return { state: 'ok', text: 'Synced' };
}

// ---- lifecycle ---------------------------------------------------------------------------------------------

export async function start() {
  if (started) return;
  started = true;
  // A page closed mid-request leaves entries "in flight". Resending is safe (idempotent), so just requeue them.
  await db.outbox.where('status').equals('in_flight').modify({ status: 'pending' });

  const sub = live(() => db.outbox.toArray(), (rows) => {
    syncState.set({
      pending: rows.filter((e) => e.status === 'pending' || e.status === 'in_flight').length,
      failed: rows.filter((e) => e.status === 'failed').length,
      conflicts: rows.filter((e) => e.status === 'conflict').length,
    });
  });
  stopLive = sub;

  const on = (target, ev, fn) => { target?.addEventListener?.(ev, fn); listeners.push(() => target?.removeEventListener?.(ev, fn)); };
  on(globalThis, 'online', () => kick(400));
  on(globalThis, 'offline', () => syncState.set({ phase: 'offline' }));
  on(globalThis.document, 'visibilitychange', () => { if (globalThis.document.visibilityState === 'visible') kick(600); });

  scheduleLoop();
  kick(0);
}

export function stop() {
  started = false;
  clearTimeout(kickTimer); clearTimeout(loopTimer);
  listeners.forEach((off) => off()); listeners = [];
  stopLive?.(); stopLive = null;
  failures = 0;
}

async function intervalMs() {
  const row = await db.settings.where('key').equals('sync.auto_interval_seconds').first().catch(() => null);
  const base = Math.max(15, Number(row?.value) || 60) * 1000;
  return Math.min(base * 2 ** Math.min(failures, 4), 10 * 60 * 1000);   // back off while the server is struggling
}
function scheduleLoop() {
  clearTimeout(loopTimer);
  if (!started) return;
  intervalMs().then((ms) => { loopTimer = setTimeout(() => { kick(0); scheduleLoop(); }, ms * (0.9 + Math.random() * 0.2)); });
}

/** Ask for a sync soon. Cheap to call often: bursts collapse into one run. */
export function kick(delay = 1200) {
  if (!started) return;
  clearTimeout(kickTimer);
  kickTimer = setTimeout(() => syncNow(), delay);
}

/** Run one full sync cycle and resolve when it has finished. Calls are serialized: if a cycle is already running
 *  (it may have started before the caller's latest change), wait for it, then run a fresh one. */
export async function syncNow() {
  while (running) await running;
  running = (async () => {
    // One tab at a time: two tabs syncing together would be harmless (idempotent) but wasteful.
    const locks = globalThis.navigator?.locks;
    if (locks) await locks.request('fdn-sync', { ifAvailable: true }, async (lock) => { if (lock) await cycle(); });
    else await cycle();
  })().finally(() => { running = null; });
  return running;
}

// ---- one full cycle ----------------------------------------------------------------------------------------

async function cycle() {
  if (!online()) { syncState.set({ phase: 'offline' }); return; }
  const cursor = await getMeta('cursor', 0);
  const s = syncState.get();
  const hasWork = s.pending > 0 || cursor === 0;
  if (hasWork) syncState.set({ phase: 'syncing', bootstrapping: cursor === 0 });

  try {
    const status = await probe();
    if (!status) return;                                   // phase already set (offline / auth / device)
    await push();
    await pull();
    await refreshConflicts(status.open_conflicts);
    failures = 0;
    syncState.set({ phase: 'idle', lastSyncAt: new Date().toISOString(), lastError: null, errorCode: null, progress: null, bootstrapping: false });
  } catch (e) {
    handleFailure(e);
  }
}

function handleFailure(e) {
  if (e?.network) { syncState.set({ phase: 'offline', progress: null, bootstrapping: false }); failures++; return; }
  if (e?.status === 401 && e.code === 'DEVICE_INVALID') { syncState.set({ phase: 'device', errorCode: e.code, progress: null }); return; }
  if (e?.status === 401 && e.code === 'DEVICE_REQUIRED') { syncState.set({ phase: 'device', errorCode: e.code, progress: null }); return; }
  if (e?.status === 401 || e?.code === 'PASSWORD_CHANGE_REQUIRED') { syncState.set({ phase: 'auth', errorCode: e.code, progress: null }); return; }
  failures++;
  syncState.set({ phase: 'error', lastError: e?.message || String(e), errorCode: e?.code || null, progress: null, bootstrapping: false });
}

/** Cheap reachability + auth check. Returns the status, or null after setting the phase. */
async function probe() {
  try {
    return (await api.get('/sync/status', { timeout: 7000 })).data;
  } catch (e) {
    handleFailure(e);
    return null;
  }
}

// ---- push --------------------------------------------------------------------------------------------------

const BATCH = 50;

export async function push() {
  for (let guard = 0; guard < 400; guard++) {
    const batch = await db.outbox.where('status').equals('pending').limit(BATCH).sortBy('seq');
    if (!batch.length) return;
    await db.outbox.bulkUpdate(batch.map((e) => ({ key: e.seq, changes: { status: 'in_flight' } })));

    let results;
    try {
      const body = { changes: batch.map((e) => ({ change_id: e.change_id, entity: e.entity, entity_id: e.entity_id, op: e.op, base_version: e.op === 'create' ? undefined : e.base_version, fields: e.fields || undefined, client_ts: e.client_ts })) };
      results = (await api.post('/sync/push', body, { timeout: 30000 })).data.results;
    } catch (e) {
      // Nothing was learned about these changes: put them back, untouched, to be resent (idempotent) later.
      await db.outbox.bulkUpdate(batch.map((x) => ({ key: x.seq, changes: { status: 'pending' } })));
      throw e;
    }

    for (let i = 0; i < batch.length; i++) await applyResult(batch[i], results[i]);
    if (results.some((r) => r.status === 'rejected' && r.code === 'forbidden')) notify({ kind: 'bad', text: 'Some changes were refused: you do not have permission.' });
  }
}

async function applyResult(entry, r) {
  const tbl = table(entry.entity);
  await db.transaction('rw', [db.outbox, tbl, db.conflicts], async () => {
    if (!r) { await db.outbox.update(entry.seq, { status: 'pending' }); return; }
    if (r.status === 'applied' || r.status === 'duplicate') {
      await db.outbox.delete(entry.seq);
      const row = await tbl.get(entry.entity_id);
      if (row && r.version > row.version) await tbl.put({ ...row, version: r.version });
      return;
    }
    if (r.status === 'conflict') {
      const row = await tbl.get(entry.entity_id);
      if (r.server && row) await tbl.put({ ...row, ...r.server });     // the server kept its value: show it
      if (r.resolved) { await db.outbox.delete(entry.seq); notify({ kind: 'info', text: 'Someone else changed this record first; their version was kept.' }); return; }
      await db.outbox.update(entry.seq, { status: 'conflict', conflict_id: r.conflict_id, error: { code: r.code, fields: r.conflicting_fields } });
      await db.conflicts.put({ id: r.conflict_id, entity: entry.entity, entity_id: entry.entity_id, op: entry.op, status: 'open', reason: r.code, conflicting_fields: r.conflicting_fields || [], local_payload: entry.fields || {}, server_payload: r.server || null, base_version: entry.base_version, server_version: r.server_version });
      return;
    }
    // rejected: permanent. Keep it visible so a person can fix it or discard it - never silently drop work.
    await db.outbox.update(entry.seq, { status: 'failed', attempts: (entry.attempts || 0) + 1, error: { code: r.code, message: r.message, errors: r.errors } });
  });
}

// ---- pull --------------------------------------------------------------------------------------------------

export async function pull() {
  let cursor = await getMeta('cursor', 0);
  let total = 0;
  for (let guard = 0; guard < 2000; guard++) {
    const { data } = await api.get(`/sync/pull?since=${cursor}&limit=500`, { timeout: 30000 });
    await applyPulled(data.changes, data.next_seq);
    total += data.changes.length;
    if (cursor === 0 || total > 0) syncState.set({ progress: { received: total } });
    if (!data.has_more) return total;
    if (data.next_seq <= cursor) throw new Error('The server did not advance the sync cursor.');
    cursor = data.next_seq;
  }
  return total;
}

/** Apply one page of server changes and move the cursor, atomically. Exported for tests. */
export async function applyPulled(changes, nextSeq) {
  const tables = Object.keys(entities).map((n) => table(n));
  await db.transaction('rw', [...tables, db.outbox, db.meta], async () => {
    for (const c of changes) {
      if (!entities[c.entity]) continue;                      // an entity this build doesn't know yet: skip, but still advance
      const tbl = table(c.entity);
      const existing = await tbl.get(c.entity_id);
      const p = c.payload || {};
      if (existing && c.op !== 'create' && p.version != null && p.version < existing.version) continue;   // already have newer

      const prot = existing ? await protectedFields(c.entity, c.entity_id) : { fields: new Set(), deleting: false };
      const next = existing ? { ...existing } : {};
      for (const [k, v] of Object.entries(p)) {
        if (prot.fields.has(k)) continue;                      // keep the person's unsynced edit
        next[k] = v;
      }
      if (prot.deleting && existing) next.deleted_at = existing.deleted_at;
      next.id = c.entity_id;
      next.version = p.version ?? next.version;
      await tbl.put(next);
    }
    await db.meta.put({ key: 'cursor', value: nextSeq });
  });
}

// ---- conflicts ---------------------------------------------------------------------------------------------

async function refreshConflicts(serverOpen) {
  const local = await db.conflicts.where('status').equals('open').count();
  if (!serverOpen && !local) return;
  const { data } = await api.get('/sync/conflicts?status=open');
  await db.transaction('rw', [db.conflicts, db.outbox], async () => {
    const openIds = new Set(data.map((c) => c.id));
    for (const old of await db.conflicts.where('status').equals('open').toArray()) {
      if (!openIds.has(old.id)) await db.conflicts.update(old.id, { status: 'resolved' });
    }
    for (const c of data) await db.conflicts.put({ id: c.id, entity: c.entity, entity_id: c.entity_id, op: c.op, status: c.status, reason: c.reason, conflicting_fields: c.conflicting_fields || [], local_payload: c.local_payload || {}, server_payload: c.server_payload, base_version: c.base_version, server_version: c.server_version });
    // A queued change whose conflict was resolved elsewhere has nothing left to send.
    for (const e of await db.outbox.where('status').equals('conflict').toArray()) { if (e.conflict_id && !openIds.has(e.conflict_id)) await db.outbox.delete(e.seq); }
  });
}

/** Resolve a conflict on the server, then refresh. resolution: accept_server | accept_local | merged */
export async function resolveConflict(conflictId, resolution, fields) {
  await api.post(`/sync/conflicts/${conflictId}/resolve`, { resolution, fields });
  await db.transaction('rw', [db.outbox, db.conflicts], async () => {
    await db.conflicts.update(conflictId, { status: 'resolved' });
    await db.outbox.where('conflict_id').equals(conflictId).delete();
  });
  kick(0);
}

export { UNSYNCED };
