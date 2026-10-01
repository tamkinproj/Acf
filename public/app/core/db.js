import Dexie, { liveQuery } from '../../vendor/dexie.js';
import { BASE } from './config.js';
import { entities, entityNames } from './entities.js';

// The local database - the thing the whole UI reads from, online or offline.
//
//   <entity tables>  full copies of what this user may read, kept current by the sync engine
//   outbox           ordered queue of this device's changes waiting to reach the server
//   conflicts        server-recorded conflicts that need a human decision
//   meta             small key/value facts: pull cursor, cached profile, device token, PIN verifier...
//
// The database name includes the app's base URL so two installs on the same origin never share data.
export let db;

export function openDb() {
  db = new Dexie(`fdn:${BASE || '/'}`);
  const stores = {
    meta: 'key',
    outbox: '++seq, change_id, status, conflict_id, [entity+entity_id]',
    conflicts: 'id, entity, entity_id, status',
  };
  for (const name of entityNames()) stores[name] = ['id', 'updated_at', entities[name].indexes].filter(Boolean).join(', ');
  db.version(1).stores(stores);
  return db.open();
}

export const table = (entity) => db.table(entity);
export const getMeta = async (key, fallback = null) => (await db.meta.get(key))?.value ?? fallback;
export const setMeta = (key, value) => db.meta.put({ key, value });
export const delMeta = (key) => db.meta.delete(key);

/** Subscribe to a query: re-runs automatically whenever the underlying data changes (writes, pulls, other tabs). */
export function live(querier, onValue, onError = console.error) {
  const sub = liveQuery(querier).subscribe({ next: onValue, error: onError });
  return () => sub.unsubscribe();
}

/** Remove everything that belongs to a user/device session (called on sign-out and when a different user signs in). */
export async function wipeLocalData({ keep = ['deviceToken'] } = {}) {
  await db.transaction('rw', db.tables, async () => {
    for (const t of db.tables) {
      if (t.name === 'meta') { await t.filter((r) => !keep.includes(r.key)).delete(); } else { await t.clear(); }
    }
  });
}
