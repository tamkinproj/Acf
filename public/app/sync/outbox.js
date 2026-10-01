import { db, table } from '../core/db.js';
import { entities } from '../core/entities.js';
import { uuid7, nowIso, pick } from '../core/util.js';

// The write path. Every change a person makes - online or offline, it makes no difference - does two things in ONE
// atomic local transaction:
//   1. updates the local row, so the screen reflects it immediately;
//   2. appends the change to the outbox, in order, with a unique change_id (the server's idempotency key).
// The sync engine delivers the outbox whenever the server is reachable. Nothing here talks to the network.
//
// Outbox entry:
//   { seq, change_id, entity, entity_id, op: create|update|delete, base_version, fields, before,
//     status: pending|in_flight|failed|conflict, attempts, error, conflict_id, created_at, client_ts }
//   `before` = the previous values of the touched fields, so "discard" can undo the local edit exactly.

let afterWrite = () => {};
export const onLocalWrite = (fn) => { afterWrite = fn; };

/** Statuses that still represent a change the server has not accepted. */
export const UNSYNCED = ['pending', 'in_flight', 'failed', 'conflict'];

const helpers = () => ({ get: (entity, id) => table(entity).get(id) });

async function lastEntry() { return db.outbox.orderBy('seq').last(); }

export async function createRecord(entity, fields, { id = uuid7() } = {}) {
  const now = nowIso();
  await db.transaction('rw', [table(entity), db.outbox], async () => {
    let row = { id, ...fields, version: 1, created_at: now, updated_at: now, deleted_at: null };
    row = { ...row, ...(await entities[entity].derive?.(row, helpers())) };
    await table(entity).put(row);
    await db.outbox.add({ change_id: uuid7(), entity, entity_id: id, op: 'create', base_version: 1, fields: { ...fields }, before: null, status: 'pending', attempts: 0, created_at: now, client_ts: now });
  });
  afterWrite();
  return id;
}

export async function updateRecord(entity, id, fields) {
  const now = nowIso();
  await db.transaction('rw', [table(entity), db.outbox], async () => {
    const row = await table(entity).get(id);
    if (!row) throw new Error(`Cannot edit: ${entity}/${id} is not on this device.`);
    const keys = Object.keys(fields);
    const before = pick(row, keys);
    const last = await lastEntry();

    // Fold into the newest queued entry for this record, but ONLY if nothing was queued after it - otherwise the edit
    // could reach the server before something it depends on (e.g. a parent created later in the queue).
    if (last && last.entity === entity && last.entity_id === id && last.status === 'pending' && (last.op === 'create' || last.op === 'update')) {
      const fieldsMerged = { ...last.fields, ...fields };
      const beforeMerged = last.op === 'create' ? null : { ...before, ...last.before };
      await db.outbox.update(last.seq, { fields: fieldsMerged, before: beforeMerged, client_ts: now });
    } else {
      await db.outbox.add({ change_id: uuid7(), entity, entity_id: id, op: 'update', base_version: row.version, fields: { ...fields }, before, status: 'pending', attempts: 0, created_at: now, client_ts: now });
    }
    let next = { ...row, ...fields, updated_at: now };
    next = { ...next, ...(await entities[entity].derive?.(next, helpers())) };
    await table(entity).put(next);
  });
  afterWrite();
}

export async function removeRecord(entity, id) {
  const now = nowIso();
  await db.transaction('rw', [table(entity), db.outbox], async () => {
    const row = await table(entity).get(id);
    if (!row) return;
    const entries = await db.outbox.where('[entity+entity_id]').equals([entity, id]).toArray();
    const unsyncedCreate = entries.find((e) => e.op === 'create' && e.status === 'pending');
    if (unsyncedCreate) {
      // Created and deleted before the server ever heard of it: it never existed. Drop it and everything queued for it.
      await db.outbox.bulkDelete(entries.filter((e) => e.status === 'pending').map((e) => e.seq));
      await table(entity).delete(id);
      return;
    }
    await db.outbox.add({ change_id: uuid7(), entity, entity_id: id, op: 'delete', base_version: row.version, fields: null, before: { deleted_at: row.deleted_at ?? null }, status: 'pending', attempts: 0, created_at: now, client_ts: now });
    await table(entity).put({ ...row, deleted_at: now, updated_at: now });
  });
  afterWrite();
}

/** Undo a change that will not be delivered (rejected, or the user changed their mind). */
export async function discardEntry(seq) {
  await db.transaction('rw', [db.outbox, ...Object.keys(entities).map((n) => table(n))], async () => {
    const e = await db.outbox.get(seq);
    if (!e) return;
    const tbl = table(e.entity);
    const row = await tbl.get(e.entity_id);
    if (e.op === 'create') {
      const later = await db.outbox.where('[entity+entity_id]').equals([e.entity, e.entity_id]).toArray();
      await db.outbox.bulkDelete(later.map((x) => x.seq));
      await tbl.delete(e.entity_id);
      return;
    }
    if (row && e.before) await tbl.put({ ...row, ...e.before });
    await db.outbox.delete(seq);
  });
}

export async function retryEntry(seq) {
  await db.outbox.update(seq, { status: 'pending', attempts: 0, error: null, next_attempt_at: null });
  afterWrite();
}

/** Changed fields currently waiting for a record, so a pull never overwrites an edit the user has not synced yet. */
export async function protectedFields(entity, id) {
  const entries = (await db.outbox.where('[entity+entity_id]').equals([entity, id]).toArray()).filter((e) => UNSYNCED.includes(e.status));
  const fields = new Set();
  let deleting = false;
  for (const e of entries) { if (e.op === 'delete') deleting = true; Object.keys(e.fields || {}).forEach((k) => fields.add(k)); }
  return { fields, deleting };
}

export const unsyncedCount = () => db.outbox.filter((e) => UNSYNCED.includes(e.status)).count();
