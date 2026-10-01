# Sync protocol

All endpoints are same-origin JSON under `/api`, using the browser cookie session (send `X-XSRF-TOKEN`
from the `XSRF-TOKEN` cookie on writes; call `GET /api/auth/csrf` first). Sync endpoints additionally need
`Authorization: Bearer <device token>` and the `sync.use` permission.

Envelope: `{"success":true,"data":…,"meta":…}` or `{"success":false,"code":"…","message":"…","errors":{…}}`.

## Identity

1. An authorised user registers a device: `POST /api/devices {name,type}` → `{token}` (**shown once**; store it).
   The installer's device is claimed with `POST /api/devices/{id}/claim`.
2. The user signs in: `POST /api/auth/login`. `GET /api/auth/me` returns user, permissions, foundation.
3. Every sync call carries both. The session says *who*, the token says *where*. A revoked device gets
   `401 DEVICE_INVALID`; an expired session `401 UNAUTHENTICATED` — **keep the outbox and wait for sign-in;
   never drop queued changes on auth errors**.

## `GET /api/sync/status`
Connectivity probe + state: `{online, server_time, app_version, schema_version, latest_seq, device, open_conflicts}`.
Use this (not `navigator.onLine`) to decide whether the server is reachable.

## `GET /api/sync/schema`
Per entity: `model_fields`, `meta_fields`, `writable`, `soft_deletes`, `can_pull`, `ops{create,update,delete}`
(what *this user* may do), `conflict_policy`. Build local stores and hide forbidden actions from this.

## `GET /api/sync/pull?since=<seq>&limit=<n>&entities[]=…`
Returns `{changes:[…], next_seq, has_more}`. Each change:
`{seq, change_id, entity, entity_id, op: create|update|delete, version, device_id, payload}`.

- `create` payload = full row. `update` payload = **changed fields only** + meta (`version`, `updated_at`,
  `deleted_at`). `delete` payload carries `deleted_at` (tombstone). Apply in `seq` order.
- Start at `since=0`; loop while `has_more`, passing back `next_seq`. Persist `next_seq` only after the page is
  stored locally. Passing a cursor tells the server what you hold (`devices.last_pull_seq`).
- Only entities the user may read are returned. Pull may include your own earlier pushes — apply by `version`.
- Rows younger than `foundation.sync.settle_seconds` (default 2s) are withheld so a slow concurrent
  transaction can never be skipped by an advancing cursor.

## `POST /api/sync/push`
Body `{"changes":[…]}` (max 200, in outbox order). Each change:

```json
{"change_id":"<uuid, idempotency key>","entity":"locations","entity_id":"<uuid>","op":"update",
 "base_version":3,"fields":{"name":"…"},"client_ts":"2026-10-01T08:00:00Z"}
```
`create` needs `fields`; `update` needs `base_version` + `fields`; `delete` needs `base_version`.
`base_version` = the `version` of the row your edit was made against (use `1` for a row you created
offline and have not yet synced). Generate `entity_id` and `change_id` (UUIDv7) on the device.

Response `data.results[]`, positionally aligned with the request:

| status | meaning | client action |
|---|---|---|
| `applied` `{version, merged?, noop?}` | accepted (`merged`: disjoint concurrent edits were combined) | mark synced; take `version` |
| `duplicate` `{version}` | this `change_id` was already applied (lost response / retry) | mark synced |
| `conflict` `{conflict_id, code, conflicting_fields, server_version, server, resolved}` | same field changed elsewhere, or delete-vs-edit. **Server value kept, your change preserved** server-side | mark `conflict`, show to user; `pull` to refresh |
| `rejected` `{code, message, errors?}` | permanent: validation, permission, unknown entity, invalid field, hierarchy… | mark `failed`, surface to user; do not retry unchanged |

Network errors / 5xx / 429: retry with exponential backoff + jitter. It is always safe to resend a batch.

Conflict rules: if `row.version == base_version` apply. Otherwise compare the fields changed by *other
devices* since `base_version` (a device's own earlier changes never conflict with itself): disjoint ⇒ merge;
overlap, or a delete involved ⇒ conflict. Per-entity `conflict_policy`: `manual` (default), `server_wins`,
`client_wins` (applies, but records the overwrite).

Server-controlled fields (`id`, `version`, timestamps, `origin_device_id`, `created_by`…, `path`, `depth`,
identity fields on audit entries) are ignored or rejected as `invalid_field` — never trusted.

## Conflicts
- `GET /api/sync/conflicts?status=open` — yours (your device), or all with `sync.manage`.
- `POST /api/sync/conflicts/{id}/resolve {resolution: accept_server|accept_local|merged, fields?}` (`sync.manage`).
  `accept_local`/`merged` re-apply on top of the *current* server version through the normal validators.

## Entities (Phase 1)

| entity | pull needs | devices may | notes |
|---|---|---|---|
| `foundations` | `foundation.view` | update (`foundation.manage`) | logo is online-only (`POST /api/foundation/logo`); `logo_hash` replicates |
| `settings` | any | update `value` (`settings.manage`) | validated against `SettingsCatalog` |
| `users` | `users.view` | update, delete (`users.manage`) | creation + passwords are online-only (`POST /api/users`); credentials never replicate |
| `roles` | any | — | edited online via `PUT /api/roles/{id}/permissions` |
| `devices` | `devices.view` | — | server-owned registry |
| `locations` | `locations.view` | create/update/delete (`locations.manage`) | `path`/`depth` derived by the server; moving a node rewrites descendants (they replicate) |
| `audit_logs` | `audit.view` | create (`sync.use`) | append-only; server stamps user/device/ip |

All timestamps are UTC (`…Z`). Convert for display using the `app.timezone` setting.
