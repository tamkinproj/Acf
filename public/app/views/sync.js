import { db, table } from '../core/db.js';
import { entities } from '../core/entities.js';
import { icon } from '../core/icons.js';
import { confirmDialog, field, readForm, sheet, toast } from '../core/ui.js';
import { $, ago, fmtDateTime, html, plural } from '../core/util.js';
import { describe, resolveConflict, syncNow, syncState } from '../sync/engine.js';
import { discardEntry, retryEntry } from '../sync/outbox.js';
import { kit } from './kit.js';

// Sync center: the honest answer to "did my work arrive?" - what is waiting, what failed and why, and what needs a decision.
const NICE = { locations: 'Place', users: 'Person', foundations: 'Foundation profile', settings: 'Setting', audit_logs: 'Activity entry', devices: 'Device', roles: 'Role' };
const VERB = { create: 'Add', update: 'Change', delete: 'Remove' };
const FRIENDLY_CODE = {
  forbidden: 'You do not have permission to make this change.', validation: 'The server did not accept the values.', hierarchy: 'This does not fit the place structure.',
  has_children: 'There are places inside it; move or remove them first.', not_found: 'The record no longer exists on the server.', already_exists: 'This record already exists.',
  invalid_field: 'A field was not allowed.', last_super_admin: 'The last Super Admin account must stay active.',
};

async function labelOf(entity, id, fields) {
  const row = await table(entity).get(id).catch(() => null);
  const f = { ...row, ...fields };
  return f.name ?? f.key ?? f.email ?? f.summary ?? id.slice(0, 8);
}
async function describeEntries(entries) {
  return Promise.all(entries.map(async (e) => ({ ...e, label: await labelOf(e.entity, e.entity_id, e.fields) })));
}
const valueText = (v) => (v === null || v === undefined || v === '' ? '—' : typeof v === 'boolean' ? (v ? 'Yes' : 'No') : typeof v === 'object' ? JSON.stringify(v) : String(v));
const errorText = (e) => {
  const first = e.error?.errors ? Object.values(e.error.errors).flat()[0] : null;
  return first || e.error?.message || FRIENDLY_CODE[e.error?.code] || 'The server refused this change.';
};

export default {
  async mount(ctx) {
    const k = kit(ctx);
    let entries = [];
    let conflicts = [];
    const manager = ctx.can('sync.manage');
    const draw = () => k.render(view(entries, conflicts, manager));

    k.live(async () => describeEntries(await db.outbox.orderBy('seq').toArray()), (rows) => { entries = rows; draw(); });
    k.live(async () => Promise.all((await db.conflicts.where('status').equals('open').toArray()).map(async (c) => ({ ...c, label: await labelOf(c.entity, c.entity_id, null) }))), (rows) => { conflicts = rows; draw(); });
    const off = syncState.subscribe(draw);

    k.on('click', '[data-sync]', () => syncNow().then(() => { const st = syncState.get(); if (st.phase === 'idle') toast('Up to date'); }));
    k.on('click', '[data-retry]', async (e, t) => { await retryEntry(Number(t.dataset.retry)); syncNow(); });
    k.on('click', '[data-discard]', async (e, t) => {
      if (await confirmDialog({ title: 'Undo this change?', text: 'The record goes back to how it was before you changed it. This cannot be undone.', confirmLabel: 'Undo change', danger: true })) { await discardEntry(Number(t.dataset.discard)); toast('Change undone'); }
    });
    k.on('click', '[data-resolve]', async (e, t) => {
      const [id, how] = t.dataset.resolve.split(':');
      try { await resolveConflict(id, how); toast(how === 'accept_server' ? "Kept the server's version" : 'Your version was applied'); } catch (err) { toast(err.network ? 'You need a connection to settle a conflict.' : err.message, 'bad'); }
    });
    k.on('click', '[data-merge]', (e, t) => openMerge(conflicts.find((c) => c.id === t.dataset.merge)));
    return () => { off(); k.cleanup(); };
  },
};

function openMerge(c) {
  const fields = c.conflicting_fields.length ? c.conflicting_fields : Object.keys(c.local_payload);
  sheet({
    title: 'Combine both versions',
    body: html`<form class="form" data-merge-form><p class="muted">Edit the final value for each field. Fields you do not list keep the server's value.</p>
      ${fields.map((f) => field({ label: f.replace(/_/g, ' '), name: f, value: c.local_payload[f] ?? c.server_payload?.[f] ?? '' , hint: `Server: ${valueText(c.server_payload?.[f])}` }))}
      <div class="btn-row"><button class="btn" type="submit">Save combined version</button><button class="btn ghost" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('[data-merge-form]', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await resolveConflict(c.id, 'merged', readForm(e.currentTarget)); close(); toast('Combined version applied'); } catch (err) { toast(err.message, 'bad'); }
    }),
  });
}

function view(entries, conflicts, manager) {
  const st = syncState.get();
  const d = describe(st);
  const waiting = entries.filter((e) => e.status !== 'conflict');
  const tone = { ok: 'chip', busy: 'chip gold', offline: 'chip grey', pending: 'chip amber', failed: 'chip red', conflict: 'chip blue', auth: 'chip red' }[d.state];
  return html`
    <div class="page-head"><div><h2>Sync</h2><p>Everything you do is saved on this device first. This page shows what has reached the server and what has not.</p></div>
      <div class="btn-row"><button class="btn" type="button" data-sync ${st.phase === 'syncing' ? 'disabled' : ''}>${icon('sync')} ${st.phase === 'syncing' ? 'Syncing…' : 'Sync now'}</button></div></div>

    <div class="card notch"><div class="card-head"><h3>Status</h3><span class="${tone}">${d.text}</span></div>
      <dl class="kv"><dt>Last synced</dt><dd>${st.lastSyncAt ? `${fmtDateTime(st.lastSyncAt)} (${ago(st.lastSyncAt)})` : 'Not yet on this visit'}</dd>
        <dt>Waiting</dt><dd>${plural(st.pending, 'change')}</dd>
        ${st.lastError ? html`<dt>Last problem</dt><dd>${st.lastError}</dd>` : ''}</dl></div>

    ${conflicts.length ? html`<section class="stack"><h3>Needs your decision</h3>
      ${!manager ? html`<div class="banner info">${icon('info')}<div class="grow">These records were edited in two places at once. A manager has to choose which version to keep.</div></div>` : ''}
      ${conflicts.map((c) => conflictCard(c, manager))}</section>` : ''}

    <section class="stack"><h3>Waiting to sync</h3>
      ${waiting.length ? html`<div class="card"><ul class="list">${waiting.map(queueRow)}</ul></div>`
        : html`<div class="empty">${icon('check')}<b>Nothing waiting</b><span>Every change made on this device is already on the server.</span></div>`}</section>`;
}

const queueRow = (e) => html`<li><div class="grow"><div class="t">${VERB[e.op] ?? e.op} ${(NICE[e.entity] ?? e.entity).toLowerCase()}: ${e.label}</div>
  <div class="d">${e.status === 'failed' ? errorText(e) : e.status === 'in_flight' ? 'Sending…' : 'Waiting for a connection'}</div></div>
  <span class="chip ${e.status === 'failed' ? 'red' : 'amber'}">${e.status === 'failed' ? 'Needs attention' : 'Waiting'}</span>
  ${e.status === 'failed' ? html`<button class="btn sm ghost" type="button" data-retry="${e.seq}">Retry</button>` : ''}
  <button class="btn sm danger" type="button" data-discard="${e.seq}">Undo</button></li>`;

const conflictCard = (c, manager) => html`<div class="card"><div class="card-head"><div><h3>${NICE[c.entity] ?? c.entity}: ${c.label}</h3>
  <p class="muted">${c.reason === 'deleted_on_server' ? 'It was removed by someone else while you changed it.' : c.reason === 'updated_on_server' ? 'It was changed by someone else while you removed it.' : 'You and someone else changed the same thing.'}</p></div><span class="chip blue">Conflict</span></div>
  <div class="stack">${(c.conflicting_fields.length ? c.conflicting_fields : Object.keys(c.local_payload)).map((f) => html`
    <div><div class="muted">${f.replace(/_/g, ' ')}</div><div class="diff"><div class="mine"><small>Your change</small>${valueText(c.local_payload[f])}</div><div class="theirs"><small>Server's version</small>${valueText(c.server_payload?.[f])}</div></div></div>`)}</div>
  ${manager ? html`<div class="btn-row"><button class="btn" type="button" data-resolve="${c.id}:accept_local">Use mine</button>
    <button class="btn ghost" type="button" data-resolve="${c.id}:accept_server">Keep the server's</button>
    ${c.op === 'update' ? html`<button class="btn ghost" type="button" data-merge="${c.id}">Combine…</button>` : ''}</div>` : ''}</div>`;
