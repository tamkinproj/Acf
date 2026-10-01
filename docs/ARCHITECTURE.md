# Architecture (Phase 1 backend)

## Principles

1. **Offline-first, not online-with-a-cache.** Clients read and write a local database and replicate through
   a queue. Online and offline are the same code path.
2. **One write path for replicated data.** Devices change synchronized entities only through
   `POST /api/sync/push` — never through per-entity REST endpoints — so validation, permissions, versioning,
   audit and conflict handling exist exactly once (`App\Sync\ChangeApplier`).
3. **Business data and sync metadata are separate.** Business tables carry only a minimal replication
   envelope; the change feed and conflicts live in `sync_*` tables.
4. **Never silently overwrite.** Concurrent edits to the same field become a recorded conflict; the local
   change is preserved.
5. **Nothing destructive is automatic.** Installer, upgrades and recovery never drop or reset data.

## Layout

```
app/Install/        installer: state, requirements, DB config, .env writer, runner, log
app/Sync/           replication engine: traits, registry, feed, applier, conflicts
app/Core/           Access (permissions, roles), Audit, Settings, Locations, Devices, Users, Dashboard
app/Models/         Eloquent models (replicated ones use App\Sync\Concerns\Syncable)
app/Http/           Controllers/Api (JSON), Controllers/Install (wizard), Middleware
routes/             web.php (shell, logo) · api.php (JSON API) · install.php (wizard)
database/migrations system tables · core tables · sync tables
```

## Data model

Replicated tables (`roles users locations foundations settings devices audit_logs`) share this envelope
(`App\Sync\SyncSchema`):

| column | meaning |
|---|---|
| `id` | UUIDv7, generated on whichever device creates the row — no auto-increment, no collisions |
| `version` | server-assigned revision, +1 per accepted change that touches a replicated field |
| `origin_device_id` | device that made the last accepted change |
| `created_by` `updated_by` `deleted_by` | acting user |
| `created_at` `updated_at` `deleted_at` | UTC. `deleted_at` is a soft-delete tombstone that replicates |

Actor/device columns are deliberately **not** foreign keys: audit history must survive removal, and sync
order must never depend on them. Structural relations (`users.role_id`, `locations.parent_id`) are real FKs.

Not replicated (this installation only): `system_state`, `installation_log`, `device_settings`, `sessions`,
cache/jobs. Server-only columns never appear in any sync payload — `users.password`, `devices.token_hash`,
`foundations.logo_path`, `users.last_login_at` (each model whitelists its `syncFields()`).

Sync tables: `sync_changes` (append-only change feed; `seq` = pull cursor; unique `change_id` = idempotency
key) and `sync_conflicts`.

## Deviations from the earlier proposal

- **`roles.permissions` is a JSON list**, not a `role_permissions` table. A role's grants are one atomic,
  replicable value; permission *keys* still come from the code catalog (`PermissionCatalog`).
- **No `sync_status` / `synced_at` columns on business rows.** A record's "pending/synced" badge is derived on
  the device from its outbox. The server only holds data that has, by definition, synced.
- **All timestamps are stored and exchanged in UTC.** The timezone chosen in the wizard is a *display*
  preference (`app.timezone` setting). Storing local time would silently shift every timestamp if the setting
  ever changed.
- The device created by the installer is **unclaimed** until an authorised user claims it from the browser
  that should become it (`POST /api/devices/{id}/claim`); the token is shown once.

## Installation state

Decided from **files**, not the database, so it works before a database exists and survives a broken one:
`storage/app/install/{state.json, installed.lock, token, app.key}` plus `FOUNDATION_INSTALL_ID` in `.env`.
States: `not_installed · in_progress · error · installed · corrupt`. The lock is HMAC-signed with `APP_KEY`
and must match `.env`; any mismatch ⇒ `corrupt` ⇒ recovery screen (503), never a reinstall.

The installer is re-runnable and writes the lock **last**. Seeding runs in one transaction. It refuses a
non-empty database unless the previous attempt was its own. DB/admin passwords are kept AES-encrypted in
`state.json` only until installation completes, then wiped; they are never put in the session, a form
round-trip, the installation log or the completion page.

## Security summary

- **Auth:** cookie session (`foundation_session`, HttpOnly, SameSite=Lax) + CSRF on everything, bcrypt,
  per-(email, IP) login throttle, identical error for unknown/wrong/disabled, constant-cost check, disabled
  users lose access on the next request, forced password change gate, temporary passwords shown once.
- **Devices:** `Authorization: Bearer fdt_…`; only the sha256 is stored; revocable and rotatable. Sync needs
  a valid user session **and** a valid device token.
- **Authorization:** `User → Role → permissions`, checked on every route and again per pushed change.
  Safety invariants (last Super Admin, no self-demotion, only Super Admin touches Super Admins) apply on both
  the REST and sync paths (`UserRules`).
- **Privacy:** pulls are filtered to entities the user may read; a per-entity `visible` hook exists for
  row-level scoping when beneficiary data arrives. Nothing sensitive is served from a public route.
- **Uploads:** logo is type-sniffed from content, decoded and re-encoded with GD, stored privately under a
  random name; corrupt/disguised files get a 422.
- **Audit:** append-only (model refuses update/delete), written in the same transaction as the change, and a
  failed audit write aborts the change. Identity fields are stamped by the server, never trusted from a device.
- Headers: CSP (no inline script/style), frame-ancestors none, nosniff, referrer policy, HSTS over HTTPS.

## Extension points (how Aytam, Relief, Donations… plug in)

A module adds, without touching core:
1. migrations using `SyncSchema` and models using `Syncable`;
2. `SyncRegistry::register(new EntityDefinition(...))` — ops → permission, writable fields, validation,
   guard, server-side `prepare`, conflict policy, optional `visible` row scope;
3. `PermissionCatalog::register([...])` and `SettingsCatalog::register([...])`;
4. `DashboardService::extend('aytam', fn ($user) => [...])`.

`foundation:upgrade` then adds the new settings and grants the new permissions to the default roles
(only keys that did not exist at the previous release, so deliberate removals are respected).

## Known limitations (Phase 1)

- Browser client not built yet; there is no offline UI.
- Verified on SQLite only in CI/sandbox. Migrations use portable schema-builder calls, but MySQL/MariaDB
  should be exercised before production.
- Server-to-server (upstream) sync is not implemented. The protocol is the same — a server acts as a device.
- The change feed is never compacted, so a first sync replays history from `seq = 0`. Add compaction/snapshots
  before data volumes grow.
- A conflict cannot be resolved by *restoring* a record deleted elsewhere.
- No email password reset and no 2FA (admin-driven reset; SMTP is not assumed available offline).
- Email addresses of removed users stay reserved.
