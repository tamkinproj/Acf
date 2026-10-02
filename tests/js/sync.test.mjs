// Integration tests: the REAL client sync code (outbox, engine) against a REAL running backend.
import 'fake-indexeddb/auto';
import assert from 'node:assert/strict';
import { after, before, describe, test } from 'node:test';
import { ADMIN, startBackend } from './backend.mjs';

let backend;
const jar = new Map();
const net = { mode: 'ok' };                                     // ok | down | lose-push-response
const realFetch = globalThis.fetch;

function installBrowserShims(url) {
  globalThis.fetch = async (u, opts = {}) => {
    const href = String(u);
    if (net.mode === 'down') throw new TypeError('fetch failed');
    const headers = { ...(opts.headers || {}), Cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; ') };
    const res = await realFetch(href, { ...opts, headers, redirect: 'manual' });
    for (const c of res.headers.getSetCookie?.() ?? []) { const [pair] = c.split(';'); const i = pair.indexOf('='); jar.set(pair.slice(0, i), pair.slice(i + 1)); }
    if (net.mode === 'lose-push-response' && href.includes('/sync/push')) throw new TypeError('connection reset');   // server DID apply it; the reply was lost
    return res;
  };
  globalThis.document = { documentElement: { dataset: { base: url } }, get cookie() { return [...jar].map(([k, v]) => `${k}=${v}`).join('; '); }, addEventListener() {}, visibilityState: 'visible' };
  globalThis.window = globalThis;
}
const setOnline = (v) => Object.defineProperty(globalThis.navigator, 'onLine', { value: v, configurable: true });

let api, dbm, outbox, engine, entitiesMod;
let tokenB;                                                     // a second device, used to act as "someone else"
let mainToken;                                                  // this client's own device token

async function raw(method, path, body, token = tokenB) {
  const xsrf = decodeURIComponent(jar.get('XSRF-TOKEN') || '');
  const r = await fetch(`${backend.url}/api${path}`, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, 'X-XSRF-TOKEN': xsrf }, body: body ? JSON.stringify(body) : undefined });
  return { status: r.status, json: await r.json() };
}
/** What the server really holds for an entity, rebuilt independently from its change feed. */
async function serverRows(entity) {
  const rows = new Map();
  let since = 0;
  for (;;) {
    const { json } = await raw('GET', `/sync/pull?since=${since}&limit=1000&entities[]=${entity}`);
    for (const c of json.data.changes) rows.set(c.entity_id, c.op === 'create' ? { ...c.payload } : { ...rows.get(c.entity_id), ...c.payload });
    if (!json.data.has_more) break;
    since = json.data.next_seq;
  }
  return rows;
}
const live = async (entity) => (await dbm.table(entity).toArray()).filter((r) => !r.deleted_at);
const unsynced = () => dbm.db.outbox.toArray();

before(async () => {
  backend = await startBackend();
  installBrowserShims(backend.url);
  setOnline(true);
  api = await import('../../public/app/core/api.js');
  dbm = await import('../../public/app/core/db.js');
  outbox = await import('../../public/app/sync/outbox.js');
  engine = await import('../../public/app/sync/engine.js');
  entitiesMod = await import('../../public/app/core/entities.js');
  await dbm.openDb();

  await api.post('/auth/login', ADMIN);
  const primary = (await api.get('/devices')).data.find((d) => d.is_primary);
  mainToken = (await api.post(`/devices/${primary.id}/claim`)).data.token;
  api.setDeviceToken(mainToken);
  tokenB = (await api.post('/devices', { name: 'Field Phone', type: 'field' })).data.token;
});
after(() => { engine.stop(); backend?.stop(); });

describe('first sync', () => {
  test('downloads everything this user may read and stores the cursor', async () => {
    assert.equal(await dbm.getMeta('cursor', 0), 0);
    await engine.syncNow();
    assert.equal(engine.syncState.get().phase, 'idle');
    assert.ok((await dbm.getMeta('cursor')) > 0);
    const serverRoles = (await api.get('/roles')).data;
    assert.ok(serverRoles.length >= 6);
    assert.equal((await dbm.table('roles').toArray()).length, serverRoles.length);
    assert.equal((await dbm.table('foundations').toArray()).length, 1);
    assert.ok((await dbm.table('settings').toArray()).length >= 7);
    assert.equal((await dbm.table('users').toArray()).length, 1);
    assert.ok((await dbm.table('audit_logs').toArray()).length > 0);
    assert.equal((await unsynced()).length, 0);
  });

  test('credentials never reach the local database', async () => {
    const dump = JSON.stringify(await dbm.table('users').toArray());
    assert.ok(!dump.includes('$2y$') && !dump.includes('password'));
  });
});

describe('working offline', () => {
  const ids = {};

  test('writes land locally at once and queue in order, with no network', async () => {
    setOnline(false);
    ids.country = await outbox.createRecord('locations', { name: 'Philippines', level: 'country', parent_id: null });
    ids.region = await outbox.createRecord('locations', { name: 'BARMM', level: 'region', parent_id: ids.country });
    ids.site = await outbox.createRecord('locations', { name: 'Relief Depot', level: 'site', parent_id: ids.region });

    const rows = await live('locations');
    assert.equal(rows.length, 3);
    const site = rows.find((r) => r.id === ids.site);
    assert.equal(site.path, `/${ids.country}/${ids.region}/${ids.site}/`, 'tree path computed locally while offline');
    assert.equal(site.depth, 2);
    assert.deepEqual((await unsynced()).map((e) => [e.op, e.status]), [['create', 'pending'], ['create', 'pending'], ['create', 'pending']]);

    await engine.syncNow();
    assert.equal(engine.syncState.get().phase, 'offline');
    assert.equal((await unsynced()).length, 3, 'nothing is lost while offline');
    assert.equal((await serverRows('locations')).size, 0);
  });

  test('when the connection returns, everything is delivered once and in order', async () => {
    setOnline(true);
    await engine.syncNow();
    assert.equal(engine.syncState.get().phase, 'idle');
    assert.equal((await unsynced()).length, 0);

    const server = await serverRows('locations');
    assert.equal(server.size, 3);
    assert.equal(server.get(ids.site).path, `/${ids.country}/${ids.region}/${ids.site}/`);
    const local = (await live('locations')).find((r) => r.id === ids.site);
    assert.equal(local.path, server.get(ids.site).path, 'local row agrees with the server');
    assert.equal(local.version, 1);
  });

  test('the same IDs are used on both sides (no duplicates, no re-keying)', async () => {
    const server = await serverRows('locations');
    for (const id of Object.values(ids)) assert.ok(server.has(id));
  });
});

describe('queue behaviour', () => {
  test('edits fold into the newest queued change, but never past an unrelated one', async () => {
    const a = await outbox.createRecord('locations', { name: 'A', level: 'country', parent_id: null });
    await outbox.updateRecord('locations', a, { name: 'A2' });
    await outbox.updateRecord('locations', a, { code: 'AA' });
    let q = await unsynced();
    assert.equal(q.length, 1, 'create + two edits fold into one create');
    assert.deepEqual(q[0].fields, { name: 'A2', level: 'country', parent_id: null, code: 'AA' });

    const b = await outbox.createRecord('locations', { name: 'B', level: 'country', parent_id: null });
    await outbox.updateRecord('locations', a, { name: 'A3' });          // something was queued after A's create: must NOT jump ahead of it
    q = await unsynced();
    assert.equal(q.length, 3);
    assert.equal(q[2].op, 'update');
    assert.equal(q[2].entity_id, a);
    await outbox.discardEntry(q[0].seq);                                // drop A (and its later edits) + keep B
    await outbox.discardEntry((await unsynced()).find((e) => e.entity_id === b).seq);
    assert.equal((await unsynced()).length, 0);
    assert.equal((await live('locations')).filter((r) => ['A', 'B', 'A2', 'A3'].includes(r.name)).length, 0);
  });

  test('created then deleted before syncing: the server never hears about it', async () => {
    const id = await outbox.createRecord('locations', { name: 'Typo', level: 'country', parent_id: null });
    await outbox.removeRecord('locations', id);
    assert.equal((await unsynced()).length, 0);
    assert.equal(await dbm.table('locations').get(id), undefined);
  });

  test('editing a synced record queues an update that carries the version it was based on', async () => {
    const [row] = (await live('locations')).filter((r) => r.name === 'BARMM');
    await outbox.updateRecord('locations', row.id, { name: 'Bangsamoro' });
    const [e] = await unsynced();
    assert.deepEqual([e.op, e.base_version, e.before], ['update', row.version, { name: 'BARMM' }]);
    await engine.syncNow();
    assert.equal((await unsynced()).length, 0);
    assert.equal((await serverRows('locations')).get(row.id).name, 'Bangsamoro');
    assert.equal((await dbm.table('locations').get(row.id)).version, 2);
  });
});

describe('failure handling', () => {
  test('a lost response never creates duplicates: the retry is recognised by the server', async () => {
    const id = await outbox.createRecord('locations', { name: 'Lost reply', level: 'country', parent_id: null });
    net.mode = 'lose-push-response';
    await engine.syncNow();
    assert.equal(engine.syncState.get().phase, 'offline');
    assert.equal((await unsynced()).length, 1, 'client still believes it is unsent');
    assert.ok((await serverRows('locations')).has(id), 'but the server did apply it');

    net.mode = 'ok';
    await engine.syncNow();
    assert.equal((await unsynced()).length, 0);
    const copies = [...(await serverRows('locations')).values()].filter((r) => r.name === 'Lost reply');
    assert.equal(copies.length, 1, 'exactly one record');
  });

  test('a rejected change stays visible with its reason, and can be undone exactly', async () => {
    const id = await outbox.createRecord('locations', { name: 'Bad', level: 'country', parent_id: null });
    await outbox.updateRecord('locations', id, { latitude: 500 });     // folds into the create; server rejects the latitude
    await engine.syncNow();
    const [e] = await unsynced();
    assert.equal(e.status, 'failed');
    assert.equal(e.error.code, 'validation');
    assert.ok(JSON.stringify(e.error.errors).includes('latitude'));
    assert.ok((await live('locations')).some((r) => r.id === id), 'still on screen so a person can fix it');

    await outbox.discardEntry(e.seq);
    assert.equal((await unsynced()).length, 0);
    assert.equal(await dbm.table('locations').get(id), undefined);
  });

  test('a failed edit can be corrected and retried', async () => {
    const [row] = (await live('locations')).filter((r) => r.name === 'Bangsamoro');
    await outbox.updateRecord('locations', row.id, { level: 'planet' });
    await engine.syncNow();
    const [e] = await unsynced();
    assert.equal(e.status, 'failed');
    await outbox.discardEntry(e.seq);
    assert.equal((await dbm.table('locations').get(row.id)).level, 'region', 'discard restores the previous value');
  });
});

describe('two devices', () => {
  test('different fields merge; the same field becomes a conflict that keeps both versions', async () => {
    const id = await outbox.createRecord('locations', { name: 'Shared', level: 'country', parent_id: null });
    await engine.syncNow();
    const base = (await dbm.table('locations').get(id)).version;

    // The field phone (another device) renames it and sets a code, while this device is offline and edits the name too.
    setOnline(false);
    await outbox.updateRecord('locations', id, { name: 'Edited at the office' });
    await raw('POST', '/sync/push', { changes: [{ change_id: crypto.randomUUID(), entity: 'locations', entity_id: id, op: 'update', base_version: base, fields: { name: 'Edited in the field', code: 'F1' } }] });

    setOnline(true);
    await engine.syncNow();
    const [e] = await unsynced();
    assert.equal(e.status, 'conflict');
    assert.deepEqual(e.error.fields, ['name']);
    const local = await dbm.table('locations').get(id);
    assert.equal(local.name, 'Edited in the field', 'the screen shows what the server kept');
    const conflict = (await dbm.db.conflicts.toArray())[0];
    assert.equal(conflict.local_payload.name, 'Edited at the office', 'my change is preserved');

    await engine.resolveConflict(conflict.id, 'accept_local');
    await engine.syncNow();
    assert.equal((await unsynced()).length, 0);
    const server = (await serverRows('locations')).get(id);
    assert.equal(server.name, 'Edited at the office');
    assert.equal(server.code, 'F1', "the other device's non-conflicting change survived");
    assert.equal((await dbm.table('locations').get(id)).name, 'Edited at the office');
  });

  test('server_wins style resolution: accepting the server drops my change', async () => {
    const id = await outbox.createRecord('locations', { name: 'Contested', level: 'country', parent_id: null });
    await engine.syncNow();
    const base = (await dbm.table('locations').get(id)).version;
    setOnline(false);
    await outbox.updateRecord('locations', id, { name: 'Mine' });
    await raw('POST', '/sync/push', { changes: [{ change_id: crypto.randomUUID(), entity: 'locations', entity_id: id, op: 'update', base_version: base, fields: { name: 'Theirs' } }] });
    setOnline(true);
    await engine.syncNow();
    const c = (await dbm.db.conflicts.where('entity_id').equals(id).toArray())[0];
    await engine.resolveConflict(c.id, 'accept_server');
    await engine.syncNow();
    assert.equal((await unsynced()).length, 0);
    assert.equal((await dbm.table('locations').get(id)).name, 'Theirs');
  });

  test('changes made elsewhere arrive on pull, but never overwrite my unsynced edit', async () => {
    const id = await outbox.createRecord('locations', { name: 'Guarded', level: 'country', parent_id: null });
    await engine.syncNow();
    const row = await dbm.table('locations').get(id);
    await outbox.updateRecord('locations', id, { name: 'My unsynced name' });
    await engine.applyPulled([{ entity: 'locations', entity_id: id, op: 'update', payload: { version: row.version + 1, name: 'Their name', code: 'ZZ' } }], await dbm.getMeta('cursor'));
    const after = await dbm.table('locations').get(id);
    assert.equal(after.name, 'My unsynced name', 'protected');
    assert.equal(after.code, 'ZZ', 'untouched fields still update');
    assert.equal(after.version, row.version + 1);
    await engine.syncNow();
  });

  test('a delete from another device arrives as a tombstone', async () => {
    const id = await outbox.createRecord('locations', { name: 'Doomed', level: 'country', parent_id: null });
    await engine.syncNow();
    const v = (await dbm.table('locations').get(id)).version;
    await raw('POST', '/sync/push', { changes: [{ change_id: crypto.randomUUID(), entity: 'locations', entity_id: id, op: 'delete', base_version: v }] });
    await engine.syncNow();
    assert.ok((await dbm.table('locations').get(id)).deleted_at);
    assert.ok(!(await live('locations')).some((r) => r.id === id));
  });
});

describe('session and device state', () => {
  test('an expired session pauses syncing but loses nothing; signing in again resumes', async () => {
    const id = await outbox.createRecord('locations', { name: 'While signed out', level: 'country', parent_id: null });
    await api.post('/auth/logout');
    await engine.syncNow();
    assert.equal(engine.syncState.get().phase, 'auth');
    assert.equal(engine.describe().text, 'Sign in to sync');
    assert.equal((await unsynced()).length, 1);

    await api.post('/auth/login', ADMIN);
    await engine.syncNow();
    assert.equal(engine.syncState.get().phase, 'idle');
    assert.equal((await unsynced()).length, 0);
    assert.ok((await serverRows('locations')).has(id));
  });

  test('a revoked device is told so, and keeps its queue', async () => {
    const id = await outbox.createRecord('locations', { name: 'After revoke', level: 'country', parent_id: null });
    const me = (await api.get('/devices/current')).data;
    await api.post(`/devices/${me.id}/revoke`).catch((e) => assert.equal(e.status, 409));   // primary cannot be revoked
    const temp = (await api.post('/devices', { name: 'Temporary', type: 'other' })).data;
    const keep = mainToken;
    api.setDeviceToken(temp.token);
    await api.post(`/devices/${temp.id}/revoke`);
    await engine.syncNow();
    assert.equal(engine.syncState.get().phase, 'device');
    assert.equal((await unsynced()).length, 1);
    api.setDeviceToken(keep);
    await engine.syncNow();
    assert.equal((await unsynced()).length, 0);
    assert.ok((await serverRows('locations')).has(id));
  });

  test('the indicator counters follow the queue live', async () => {
    await engine.start();
    const waitFor = async (fn) => { for (let i = 0; i < 60; i++) { if (fn(engine.syncState.get())) return; await new Promise((r) => setTimeout(r, 25)); } assert.fail('state did not update'); };
    setOnline(false);
    await outbox.createRecord('locations', { name: 'Counted 1', level: 'country', parent_id: null });
    await outbox.createRecord('locations', { name: 'Counted 2', level: 'country', parent_id: null });
    await waitFor((s) => s.pending === 2);
    await engine.syncNow();
    assert.equal(engine.describe().text, 'Offline · 2 changes waiting');
    setOnline(true);
    await engine.syncNow();
    await waitFor((s) => s.pending === 0);
    assert.equal(engine.describe().text, 'Synced');
    engine.stop();
  });

  test('indicator wording', () => {
    const d = (o) => engine.describe({ phase: 'idle', pending: 0, failed: 0, conflicts: 0, ...o });
    assert.deepEqual(d({}), { state: 'ok', text: 'Synced' });
    assert.equal(d({ pending: 5 }).text, '5 changes waiting to sync');
    assert.equal(d({ pending: 1 }).text, '1 change waiting to sync');
    assert.equal(d({ phase: 'offline', pending: 2 }).text, 'Offline · 2 changes waiting');
    assert.equal(d({ phase: 'offline' }).text, 'Offline');
    assert.equal(d({ phase: 'syncing' }).text, 'Syncing…');
    assert.equal(d({ phase: 'error' }).text, 'Sync failed — retry');
    assert.equal(d({ conflicts: 2 }).text, '2 conflicts to review');
  });
});
