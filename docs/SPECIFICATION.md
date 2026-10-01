# Foundation Management System — Technical Specification

| | |
|---|---|
| **Product** | Foundation Management System (working name) |
| **Release** | 1.0.0 — database schema version 1 |
| **Status** | Foundation core complete and tested; program modules (Aytam, teaching, relief, projects, donations) not yet built |
| **Audience** | The foundation's leadership, future developers, and anyone reviewing or extending the system |

**Conventions.** ✅ implemented and tested · 🧭 planned (not built). Requirement IDs (`FR-…`, `NFR-…`) are stable references.

---

## 1. Purpose and scope

### 1.1 Purpose
Give a charitable foundation one dependable system to manage its people, places and — progressively — its programs: orphan care (Aytam), teaching, relief goods, projects (Mashru') and reporting to partners (e.g. Dar Al Ber, Dubai).

### 1.2 In scope (release 1.0.0)
Installation, authentication, roles and permissions, foundation profile, system settings, a hierarchy of places, device registration, an immutable activity log, and a synchronisation layer that lets all of the above work offline.

### 1.3 Out of scope (this release)
Any program-specific records (orphans, sponsors, students, stock, projects, donations), partner reporting, Arabic/Filipino translation, installable phone app, backup/restore screen. See §16.

### 1.4 Design goals (in priority order)
1. **Never lose work** — offline-first; nothing is overwritten silently.
2. **Be safe with sensitive data** — least privilege, server-side enforcement, full audit trail.
3. **Run anywhere cheap** — shared hosting, File Manager only, no command line.
4. **Grow without rework** — new programs plug in through a registry.
5. **Be calm and clear to use** — simple screens, works well on a phone.

### 1.5 Relationship to other systems
Stands alone: own database, users, configuration, storage and cookies. Shares nothing with MuslimEdu and never reads its data.

---

## 2. Users and roles

| Actor | Typical person | Main needs |
|---|---|---|
| Super Admin | Owner / head | Full control, including editing roles |
| Foundation Admin | Day-to-day manager | Run people, settings, places, devices, audit |
| Staff | Office worker | Maintain places; resolve sync conflicts |
| Field Worker | Staff in the field (often offline) | Read data; later record visits and distributions |
| Volunteer | Limited helper | Read-only for now |
| Viewer | Board member / observer | Read-only |
| *(planned)* Aytam Supervisor (Mushrif), Teacher, Warehouse Keeper, Project Manager, Partner (read-only) | — | See §16 |

### 2.1 Permission catalogue and default role matrix ✅

Each person has **exactly one role**. A role is a list of permission keys stored as data (`roles.permissions`); the catalogue of keys is defined in code.

| Permission | Super Admin | Foundation Admin | Staff | Field Worker | Volunteer | Viewer |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `dashboard.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `sync.use` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `sync.manage` | ✓ | ✓ | ✓ |  |  |  |
| `foundation.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `foundation.manage` | ✓ | ✓ |  |  |  |  |
| `settings.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `settings.manage` | ✓ | ✓ |  |  |  |  |
| `locations.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `locations.manage` | ✓ | ✓ | ✓ |  |  |  |
| `users.view` | ✓ | ✓ |  |  |  |  |
| `users.manage` | ✓ | ✓ |  |  |  |  |
| `roles.view` | ✓ | ✓ |  |  |  |  |
| `roles.manage` | ✓ |  |  |  |  |  |
| `devices.view` | ✓ | ✓ |  |  |  |  |
| `devices.manage` | ✓ | ✓ |  |  |  |  |
| `audit.view` | ✓ | ✓ |  |  |  |  |

Rules:
* Super Admin always holds every permission and cannot be edited.
* New permissions introduced by an upgrade are granted only to the default roles that should have them; permissions an administrator deliberately removed are not re-granted.
* A person cannot change their own role or disable themselves; the last Super Admin cannot be removed or disabled.
* Modules add permissions through `PermissionCatalog::register()`.

---

## 3. Functional requirements

### 3.1 Installation
| ID | Requirement | Status |
|---|---|:-:|
| FR-INS-1 | A browser-based wizard installs the system without internet or command line | ✅ |
| FR-INS-2 | Steps: welcome → requirements → database → system → foundation → administrator → device → review → install → complete | ✅ |
| FR-INS-3 | Requirements check: PHP ≥ 8.3, required extensions, writable folders, database driver, and that the application folder is **not** inside the public web folder | ✅ |
| FR-INS-4 | Supports MySQL/MariaDB and SQLite; the connection is tested before continuing | ✅ |
| FR-INS-5 | The administrator's password is chosen during setup; there are no default credentials | ✅ |
| FR-INS-6 | Database passwords are never displayed back; secrets are scrubbed from logs | ✅ |
| FR-INS-7 | Refuses to install into a database that already contains tables | ✅ |
| FR-INS-8 | Installation is gated by a one-time token (except direct loopback) | ✅ |
| FR-INS-9 | Install state is kept in files (not the database), with an HMAC-signed lock cross-checked against `FOUNDATION_INSTALL_ID`; a damaged lock shows a recovery screen and **never** triggers a silent reinstall | ✅ |
| FR-INS-10 | Writes only a whitelist of keys to `.env` | ✅ |
| FR-INS-11 | Command-line equivalents exist for install, upgrade and status | ✅ |
| FR-INS-12 | Upgrades run migrations, bump the schema version, and grant only newly introduced permissions | ✅ |

### 3.2 Authentication and sessions
| ID | Requirement | Status |
|---|---|:-:|
| FR-AUTH-1 | Email + password sign-in over a cookie session with CSRF protection | ✅ |
| FR-AUTH-2 | Minimum password length 10, with letters and numbers; no online breach lookup (must work offline) | ✅ |
| FR-AUTH-3 | Failed sign-ins are throttled per email+IP (5 per minute) and per IP (30 per minute); responses are identical for unknown email, wrong password and disabled account | ✅ |
| FR-AUTH-4 | Passwords created or reset by an administrator are temporary (14 characters, shown once) and force a change at first sign-in | ✅ |
| FR-AUTH-5 | Changing or resetting a password ends the user's other sessions; disabling a user ends all of them | ✅ |
| FR-AUTH-6 | A person can list and end their other sessions | ✅ |
| FR-AUTH-7 | Optional 6-digit device PIN (PBKDF2-SHA-256, 310 000 iterations, salted); 5 wrong tries disable it until the password is used | ✅ |
| FR-AUTH-8 | Automatic lock after idle time (setting `security.idle_lock_minutes`, 1–480) | ✅ |
| FR-AUTH-9 | A different user signing in on a browser holding another user's unsynced work is blocked until that work is synced | ✅ |
| FR-AUTH-10 | Signing out with unsynced work warns first; signing out wipes the local database | ✅ |
| FR-AUTH-11 | One-time recovery page to reset the Super Admin password on hosts without a command line | ✅ |

### 3.3 Users and roles
| ID | Requirement | Status |
|---|---|:-:|
| FR-USR-1 | List users with search, status filter, sorting | ✅ |
| FR-USR-2 | Create a user online (name, email, phone, role); a temporary password is returned once | ✅ |
| FR-USR-3 | Edit name, email, phone, role, status — also possible offline (replicated) | ✅ |
| FR-USR-4 | Disable, remove (soft delete) and reset password | ✅ |
| FR-USR-5 | Edit a role's permissions online; Super Admin is immutable | ✅ |
| FR-USR-6 | Create custom roles from the interface | 🧭 |

### 3.4 Foundation profile and settings
| ID | Requirement | Status |
|---|---|:-:|
| FR-FND-1 | Profile: name, short name, description, address, phone, email, website, registration number/details, main place | ✅ |
| FR-FND-2 | Logo upload (PNG/JPEG/WebP, ≤ 2 MB, ≤ 4000 px), re-encoded server-side; shown on sign-in and in the app | ✅ |
| FR-SET-1 | Settings: system name, time zone, language, currency, deployment model, idle-lock minutes, sync interval | ✅ |
| FR-SET-2 | Every setting key is declared in a catalogue with validation; modules may register more | ✅ |

### 3.5 Places
| ID | Requirement | Status |
|---|---|:-:|
| FR-LOC-1 | A tree of up to six levels: country → region → province → municipality → barangay → site | ✅ |
| FR-LOC-2 | A child's level must be deeper than its parent's; a place with children cannot be removed | ✅ |
| FR-LOC-3 | Optional code, latitude/longitude, active flag | ✅ |
| FR-LOC-4 | Add, edit and remove **offline**; tree path and depth are derived (locally for display, authoritatively on the server) | ✅ |
| FR-LOC-5 | Search keeps the ancestors of every match visible | ✅ |

### 3.6 Devices
| ID | Requirement | Status |
|---|---|:-:|
| FR-DEV-1 | Each browser/phone that syncs is a registered device with an immutable code `FOUNDATION-DEVICE-XXXXXXXX` and a secret token `fdt_…` | ✅ |
| FR-DEV-2 | Only the SHA-256 of a token is stored; the token is shown once | ✅ |
| FR-DEV-3 | Rename, rotate token, revoke; the installation's own device cannot be revoked | ✅ |
| FR-DEV-4 | "Online" = synced within the last 15 minutes | ✅ |
| FR-DEV-5 | A revoked device is refused (`DEVICE_INVALID`) and its unsent changes are not accepted | ✅ |

### 3.7 Audit
| ID | Requirement | Status |
|---|---|:-:|
| FR-AUD-1 | Every create/update/delete of replicated records and every security event (sign-in, failed sign-in, password reset, device actions) is logged with who, when, where (device, IP), and before/after values | ✅ |
| FR-AUD-2 | The log is append-only; devices may append events but never edit or delete; identity fields are stamped by the server | ✅ |
| FR-AUD-3 | Searchable and filterable by area; works offline from the local copy | ✅ |

### 3.8 Offline and synchronisation
| ID | Requirement | Status |
|---|---|:-:|
| FR-SYN-1 | Reads and writes of replicated data always go to a local database in the browser | ✅ |
| FR-SYN-2 | Each write stores the record and an outbox entry atomically | ✅ |
| FR-SYN-3 | Background sync: push in batches, then pull; automatic retry with back-off; a status indicator is always visible | ✅ |
| FR-SYN-4 | Idempotent: resending any batch is safe | ✅ |
| FR-SYN-5 | Conflicts are detected per field; disjoint edits merge, overlapping edits (or edit-vs-delete) become a conflict; nothing is overwritten silently | ✅ |
| FR-SYN-6 | Managers resolve conflicts: keep server's, use mine, or combine | ✅ |
| FR-SYN-7 | A pull never overwrites a field with an unsynced local edit | ✅ |
| FR-SYN-8 | Deleted records replicate as tombstones | ✅ |
| FR-SYN-9 | Failed (rejected) changes can be retried or undone, with the reason shown | ✅ |
| FR-SYN-10 | Sync scoped per user/assignment (e.g. a field phone receives only its assigned children) | 🧭 |
| FR-SYN-11 | Server-to-server sync (hybrid deployments) and change-feed compaction | 🧭 |

### 3.9 Dashboard
| ID | Requirement | Status |
|---|---|:-:|
| FR-DSH-1 | Greeting, sync state line, overview numbers (places, people, devices, waiting changes, activity today), recent activity, role-appropriate quick actions | ✅ |

---

## 4. Non-functional requirements

| ID | Requirement | How it is met | Status |
|---|---|---|:-:|
| NFR-OFF-1 | Fully usable without a connection once loaded | Local database + outbox | ✅ (page load itself needs a connection the first time) |
| NFR-OFF-2 | Open and work with no signal after the first visit | Service worker / installable app | 🧭 |
| NFR-SEC-1 | Server enforces every rule | Permission middleware + per-entity validators on both REST and sync | ✅ |
| NFR-SEC-2 | Strict browser policy | CSP `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'` (no inline scripts or styles); `X-Frame-Options: DENY`; `nosniff`; HSTS on HTTPS | ✅ |
| NFR-SEC-3 | Secrets are never stored or shown in clear | Passwords hashed; device tokens SHA-256; PIN PBKDF2; DB passwords never echoed | ✅ |
| NFR-SEC-4 | Cookies cannot collide with or leak to other apps on the domain | Cookie `foundation_session`; path derived from the folder the site is served from; `Secure` over HTTPS; `SameSite=Lax` | ✅ |
| NFR-SEC-5 | Brute-force resistance | Named rate limiters (see §9.3) | ✅ |
| NFR-SEC-6 | Local data encrypted at rest on field devices | — | 🧭 |
| NFR-PRF-1 | Responsive on low-end phones | No framework; small ES modules loaded on demand and prefetched | ✅ |
| NFR-PRF-2 | Pull page 500 rows (max 1000); push batch ≤ 200 | `config/foundation.php` | ✅ |
| NFR-A11-1 | Keyboard and screen-reader basics | Semantic HTML, labels, visible focus, `aria-*` on dialogs/sort/live regions, 44 px touch targets on touch devices, reduced-motion respected | ✅ (basic; no formal audit) |
| NFR-I18N-1 | Right-to-left ready | CSS logical properties | ✅ (layout) |
| NFR-I18N-2 | Arabic and Filipino translations | — | 🧭 |
| NFR-TZ-1 | Time handling | All timestamps stored and exchanged in UTC; displayed in the `app.timezone` setting | ✅ |
| NFR-DEP-1 | Install and update on shared hosting with File Manager only | Bundled `vendor/`, install and update zips, no Composer/SSH | ✅ |
| NFR-DEP-2 | Works at a domain root or in a sub-folder | URLs built from `<html data-base>`; hash-based routing | ✅ |
| NFR-OPS-1 | Backups | Via hosting panel today; in-app backup/restore | 🧭 |

---

## 5. Architecture

### 5.1 Technology
| Layer | Choice |
|---|---|
| Server | PHP ≥ 8.3 (verified on 8.3 and 8.5), Laravel 13 |
| Database | MySQL / MariaDB 10.11 (tested) or SQLite |
| Client | Plain JavaScript ES modules, no build step; Dexie (IndexedDB) vendored locally |
| Styling | Hand-written CSS driven by design tokens; system font stack; no fonts or libraries fetched from the internet |
| Identifiers | UUIDv7 for all replicated records (generated on the device) |

### 5.2 Layers
```
Browser                                            Server (outside the web folder)
┌──────────────────────────────┐   HTTPS/JSON     ┌─────────────────────────────────┐
│ Screens (views/*.js)         │ ───────────────▶ │ Routes → middleware → controllers│
│ Router, UI kit, theme        │                  │ Permission checks, rate limits   │
│ Session / PIN / idle lock    │ ◀─────────────── │ Sync: ChangeApplier, ChangeFeed, │
│ Outbox + Sync engine         │                  │       ConflictResolver, Registry │
│ Local DB (Dexie/IndexedDB)   │                  │ Eloquent models + traits         │
└──────────────────────────────┘                  │ Auditor, Settings, Devices, ...  │
                                                  └───────────────┬─────────────────┘
                                                                  │
                                                           MySQL / SQLite
```

### 5.3 Deployment topology (shared hosting)
```
<domain home>/
├── foundation_app/        application code, vendor/, .env, storage/   ← never web-reachable
└── public_html/
    └── acr/               index.php, .htaccess, app/, css/, icons/, vendor/ (Dexie)   ← the only public part
```
`index.php` points at `../../foundation_app`. The application folder contains a deny-all `.htaccess` as a second safeguard. Install state lives in `storage/app/install/` (`state.json`, signed `installed.lock`, one-time token).

### 5.4 Key design decisions
1. **One write path for replicated data** — the sync push endpoint — so offline and online writes obey identical rules.
2. **Server is authoritative**; devices propose changes.
3. **Entity registry** — a module declares its records once (writable fields, validation, permissions, conflict policy) and receives sync, audit and permissions.
4. **Soft deletes with tombstones** — deletions replicate; history is preserved.
5. **Credentials never replicate** — password hashes, tokens and server-local bookkeeping are excluded from sync payloads.
6. **Install state outside the database** — so "installed?" can be answered before a database exists.

---

## 6. Data model

All replicated tables carry the **sync columns**: `id` (UUID), `version` (integer, +1 per accepted change), `created_at`, `updated_at`, `origin_device_id`, `created_by`, `updated_by`, and — where deletion is allowed — `deleted_at`.

| Table | Replicated | Purpose and main columns |
|---|:-:|---|
| `users` | ✓ | `name`, `email` (unique), `phone`, `password` *(server-only)*, `role_id`, `status` (active/disabled), `locale`, `must_change_password`, `last_login_at` *(server-only)* |
| `roles` | ✓ | `key`, `name`, `description`, `is_system`, `permissions` (JSON list) |
| `foundations` | ✓ | profile fields, `logo_path` *(server-only)*, `logo_hash`, `default_location_id` |
| `settings` | ✓ | `key` (unique), `group`, `value` (JSON) |
| `locations` | ✓ | `parent_id`, `level`, `name`, `code`, `path`, `depth`, `latitude`, `longitude`, `is_active` |
| `devices` | ✓ | `device_code`, `name`, `type`, `is_primary`, `revoked_at`; *server-only:* `token_hash`, `last_seen_at`, `last_pull_seq`, `app_version`, `user_agent`, `registered_by` |
| `audit_logs` | ✓ (append-only) | `occurred_at`, `device_id`, `user_id`, `user_name`, `action`, `subject_type`, `subject_id`, `summary`, `old_values`, `new_values`, `correlation_id`, `ip_address` |
| `sync_changes` | — | Ordered change feed: `seq` (cursor), `change_id` (unique), `entity`, `entity_id`, `op`, `version`, `fields`, `payload`, `device_id`, `user_id`, `client_ts`, `created_at` |
| `sync_conflicts` | — | `id`, `change_id`, `entity`, `entity_id`, `op`, `base_version`, `server_version`, `reason`, `conflicting_fields`, `local_payload`, `server_payload`, `status` (open/resolved), `resolution`, `resolved_by/at` |
| `system_state` | — | Key/value (install id, version, schema version, permissions seen) |
| `installation_log` | — | Installer steps and outcomes (never secrets) |
| `device_settings` | — | Per-installation key/values |
| `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | — | Framework tables |

### 6.1 Client-side database (IndexedDB, name `fdn:<base-url>`)
`meta` (device token, cached profile, branding, PIN record, sync cursor) · `outbox` (queued changes: `change_id`, `entity`, `entity_id`, `op`, `base_version`, `fields`, `before` image, `status`, `attempts`, `error`, `conflict_id`) · `conflicts` · one table per replicated entity (`foundations`, `settings`, `users`, `roles`, `devices`, `locations`, `audit_logs`). The whole database is wiped on sign-out and when a different user signs in.

---

## 7. Synchronisation protocol (summary)

Full contract: `docs/SYNC_PROTOCOL.md`.

* **Identity:** the session says *who* (cookie), the device token says *where* (`Authorization: Bearer fdt_…`). Both are required for sync.
* **Probe:** `GET /api/sync/status` → connectivity, server time, latest sequence, open conflicts. (Used instead of `navigator.onLine`.)
* **Pull:** `GET /api/sync/pull?since=<seq>` → ordered changes after the cursor; the client stores a page, *then* advances its cursor. Rows younger than 2 seconds are withheld so a slow concurrent transaction cannot be skipped.
* **Push:** `POST /api/sync/push` with up to 200 changes `{change_id, entity, entity_id, op, base_version, fields}`. Results per change: `applied` · `duplicate` · `conflict` · `rejected`.
* **Conflict rule:** if `row.version == base_version`, apply. Otherwise compare the fields changed by *other* devices since `base_version`; disjoint → merge; overlap or delete involved → conflict (policy `manual`, `server_wins` or `client_wins` per entity).
* **Ignored/rejected from devices:** `id`, `version`, timestamps, `origin_device_id`, `created_by`/`updated_by`, derived fields (`path`, `depth`), and identity fields on audit entries.
* **Client engine phases:** idle · syncing · offline · auth (sign in again) · device (token missing/revoked) · error (will retry). Network errors never drop queued changes.

---

## 8. HTTP API (all under `/api`, JSON)

Envelope: `{"success":true,"data":…,"meta":…}` or `{"success":false,"code":"…","message":"…","errors":{…}}`.

| Method & path | Permission | Purpose |
|---|---|---|
| `GET /system/status` | public | Name, logo hash, locale — for the sign-in page |
| `GET /auth/csrf` | public | Prime CSRF cookie |
| `POST /auth/login` | public (throttled) | Sign in |
| `POST /auth/logout` | signed in | Sign out |
| `GET /auth/me` | signed in | User, permissions, foundation, session settings |
| `PATCH /auth/profile` | signed in | Name, phone, language |
| `PUT /auth/password` | signed in (throttled) | Change password |
| `GET /auth/sessions` · `DELETE /auth/sessions/{handle}` | signed in | List / end sessions |
| `GET /dashboard/summary` | `dashboard.view` | Counts and sync overview |
| `GET /settings` | `settings.view` | Settings with metadata |
| `GET /system/health` | `settings.manage` | Database, storage, migrations checks |
| `GET /foundation` | `foundation.view` | Profile |
| `POST /foundation/logo` · `DELETE /foundation/logo` | `foundation.manage` | Logo |
| `GET /users` · `GET /users/{id}` | `users.view` | Read |
| `POST /users` · `PATCH /users/{id}` · `DELETE /users/{id}` | `users.manage` | Create (returns temporary password once) / edit / remove |
| `POST /users/{id}/reset-password` | `users.manage` (throttled) | New temporary password |
| `GET /roles` · `GET /permissions` | `roles.view` | Roles and the permission catalogue |
| `PUT /roles/{id}/permissions` | `roles.manage` | Replace a role's permissions |
| `GET /devices` | `devices.view` | List |
| `GET /devices/current` | device token | The calling device |
| `POST /devices` · `POST /devices/{id}/claim` | `devices.manage` | Register / claim; token shown once |
| `PATCH /devices/{id}` · `POST …/rotate-token` · `POST …/revoke` | `devices.manage` | Rename / new token / revoke |
| `GET /audit-logs` | `audit.view` | Filterable log |
| `GET /sync/status` · `GET /sync/schema` | `sync.use` + device | Probe / entity schema for this user |
| `GET /sync/pull` · `POST /sync/push` | `sync.use` + device (throttled) | Synchronise |
| `GET /sync/conflicts` | `sync.use` + device | Open conflicts |
| `POST /sync/conflicts/{id}/resolve` | `sync.manage` | Resolve |
| `GET /assets/logo` *(web route)* | public | Logo image (storage stays private) |

Installer routes live under `/install/*` and are available only until installation completes.

### 8.1 Rate limits
| Limiter | Limit |
|---|---|
| login | 30 / min per IP, plus 5 / min per email+IP |
| password change | 10 / min per user |
| credentials (reset/temporary) | 10 / min per user |
| upload | 10 / min per user |
| sync pull, sync push | 240 / min per device |
| installer token / database / run | 20 / 40 / 10 per min per IP |

---

## 9. Client specification

### 9.1 Screens and routes (hash routing: `#/<name>`)
| Route | Permission to see | Notes |
|---|---|---|
| `dashboard` | `dashboard.view` | Home |
| `places` | `locations.view` | Edit actions need `locations.manage`; row opens an action sheet |
| `sync` | any signed-in | Resolving needs `sync.manage` |
| `foundation` | `foundation.view` | Edit and logo need `foundation.manage` |
| `users` | `users.view` | Table on desktop, list on phone |
| `roles` | `roles.view` | Editing needs `roles.manage` |
| `devices` | `devices.view` | Actions need `devices.manage` |
| `activity` | `audit.view` | |
| `settings` | `settings.view` | Editing needs `settings.manage` |
| `account` | any signed-in | Profile, password, PIN, appearance, sessions, sign out |

Before the app: sign-in · device registration · lock (PIN keypad / password) · forced password change · PIN offer.

### 9.2 Session states
`booting → anon | needs-device | locked | active`. If the server forgets who we are while on the device screen, the client returns to sign-in.

### 9.3 Online-only actions (explicitly marked in the interface)
Create user, reset password, device registration and tokens, role permission changes, logo upload, ending other sessions, resolving conflicts.

### 9.4 Design system
* **Tokens** (`css/tokens.css`): light and dark palettes, one accent (blue), state colours (green/orange/red), spacing scale 4–64 px, radii 8/12/16/20 px, two shadows, motion curve; shared by the app and the installer.
* **Components** (`css/app.css`): sidebar, top bar, tab bar, page header, cards, grouped lists, sortable table (collapses to list on phones), chips, buttons (primary, secondary, plain, danger, loading), forms, search with clear, switches, banners, empty states, skeleton loaders, sheets (dialog on desktop, bottom sheet on phones), action sheets, toasts.
* **Behaviour:** sidebar ≥ 900 px; below that a top bar, bottom tabs (3 + More) and bottom sheets; dark mode follows the device or the user's choice; animations are short and disabled under *reduced motion*.

---

## 10. Configuration

### 10.1 `.env` keys written by the installer (whitelist)
`APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL, APP_TIMEZONE, APP_LOCALE, APP_FALLBACK_LOCALE`, database keys (`DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD` or SQLite file), `SESSION_DRIVER=database, SESSION_LIFETIME=120, SESSION_COOKIE=foundation_session, SESSION_PATH, SESSION_SECURE_COOKIE`, cache/queue drivers, `FOUNDATION_INSTALL_ID`.

### 10.2 `config/foundation.php`
| Key | Default |
|---|---|
| `version` / `schema_version` | 1.0.0 / 1 |
| `device.types` | office, field, mobile, server, other |
| `device.online_window_minutes` | 15 |
| `sync.max_push_batch` | 200 |
| `sync.pull_page_size` / `max_pull_page_size` | 500 / 1000 |
| `sync.settle_seconds` | 2 |
| `auth.password_min_length` | 10 |
| `auth.login_max_attempts` / `login_decay_seconds` | 5 / 60 |
| `uploads.logo_max_kb` / `logo_max_pixels` | 2048 / 4000 |
| `deployment_models` | central, local_server, standalone |

### 10.3 Settings (editable in the app)
`app.name` · `app.timezone` (default Asia/Manila) · `app.locale` (en, fil, ar) · `app.currency` (PHP, USD, SAR, AED, EUR, GBP, MYR, IDR, SGD) · `deployment.model` · `security.idle_lock_minutes` (15) · `sync.auto_interval_seconds` (60).

---

## 11. Operations

| Task | How |
|---|---|
| **Fresh install** | Upload `foundation-hostinger.zip` (puts `foundation_app` beside `public_html`, public files in the web folder); open the site; follow the wizard |
| **Update** | `update-foundation_app.zip` → extract inside `foundation_app`; `update-web.zip` → extract inside the web folder. Neither contains `.env`, `storage` or `index.php`. Optional `php artisan foundation:upgrade` where a command line exists |
| **Status** | `php artisan foundation:status` (versions, migrations, install state) or `GET /api/system/health` |
| **Recover Super Admin** | One-time `reset-admin.php` with a secret key; deletes itself afterwards |
| **Diagnose sign-in/cookies** | One-time read-only `session-check.php` |
| **Back up** | Database export from the hosting panel + copy `foundation_app/.env` and `storage/app/install/` (the lock and key are needed to restore) |
| **Lost phone** | Revoke the device in *Devices*; disable the user if needed |

Known operational notes: PHP version is set per website (not per folder) on shared hosting; shared domains with other apps (e.g. MuslimEdu) share that setting.

---

## 12. Testing and quality

| Layer | What | Count |
|---|---|---|
| PHP (PHPUnit; SQLite, and MariaDB 10.11 locally) | Installer, gates and recovery, sign-in and sessions, users and roles, devices, sync push/pull, conflicts, upgrade, subfolder hosting, database emptiness, logo, dashboard/audit, cookie-path | 131 (130 pass, 1 skipped by design) |
| JS integration (`node --test`) | Sync engine against a real PHP backend | 19 |
| Browser end-to-end (Chromium) | Offline add → reconnect · two devices, conflict, both resolutions · people/settings/roles/devices/PIN/sign-out guard · lost session · wrong cookie path | 5 flows |
| Environments exercised | PHP 8.3 and 8.5; MariaDB and SQLite; domain root and sub-folder; simulated reverse proxy | — |

---

## 13. Extending the system with a program module

**Server**
1. Migration with the sync columns (`SyncSchema::columns`).
2. Eloquent model using `Syncable` / `HasSyncMetadata`; list business fields in `syncFields()`.
3. Register an `EntityDefinition` (name, model, allowed ops with permissions, pull permission, writable fields, rules, optional guard/prepare, conflict policy).
4. Register permissions (`PermissionCatalog::register`) and, if needed, default role grants and settings (`SettingsCatalog::register`).
5. Feature tests for rules, permissions and conflicts.

**Client**
1. `registerEntity('name', { indexes, derive })`.
2. A view module exporting `{ mount(ctx) }`; add one line to `routes`.
3. Read from `db.<entity>`; write only through `createRecord / updateRecord / removeRecord`.

Sync, offline, conflicts, audit and permissions then work without further code.

---

## 14. Constraints and assumptions

* Hosting: shared PHP hosting with File Manager; no SSH, no Composer on the server.
* Browsers: current Chrome/Edge/Safari/Firefox with IndexedDB and Web Crypto (PIN needs a secure context, i.e. HTTPS).
* Single foundation per installation.
* Time zone for display is a setting; storage is UTC.
* No external services are required or called (no CDN, fonts, analytics, or e-mail).

---

## 15. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Lost or stolen phone holds records | Device revoke, PIN lock, idle lock, wipe on sign-out; encryption at rest and assignment-scoped sync planned |
| Two people edit the same record offline | Field-level conflict detection and manual resolution; no silent overwrite |
| Wrong URL entered at setup breaks sign-in | Cookie path now derived from the real folder; diagnostic page available |
| File Manager extracts replace whole folders | Update zips contain only complete code folders and never `storage`/`.env`/`index.php` |
| Lost `.env` / install lock | Recovery screen (never auto-reinstall); keep a backup of `.env` and `storage/app/install/` |
| Sensitive child data (future Aytam) | Separate permissions, least-privilege roles, access logging, consent for photos, scoped sync |

---

## 16. Roadmap (🧭 planned — not part of this release)

1. **Aytam (orphan care):** orphan profile, guardians, documents, education, health notes (restricted), visits/follow-ups, sponsorship, assignment, approval workflow; roles *Aytam Supervisor (Mushrif)* and *Social/Field Worker*.
2. **Teachers and teaching:** teachers, classes/halaqat, enrolment, attendance, schedules, progress.
3. **Relief goods:** items, warehouses, stock movements, kits, distributions by place, offline receipts.
4. **Projects (Mashru'):** budgets, spending, activities, milestones, links to the other programs.
5. **Donations and partner reporting (Dar Al Ber):** donors, restricted funds, receipts, partner-readable reports and exports (PDF/Excel), read-only Partner role.
6. **Cross-cutting:** Arabic/Filipino, installable offline app, Excel import, backup/restore screen, one-click update, notifications, encrypted local storage, custom roles in the interface, assignment-scoped sync.

See `docs/SYSTEM_OVERVIEW_AND_ROADMAP.md` for the narrative version and the decisions still needed from the foundation.

---

## 17. Related documents

`README.md` · `docs/ARCHITECTURE.md` · `docs/SYNC_PROTOCOL.md` · `docs/CLIENT.md` · `docs/HOSTINGER.md` · `docs/SYSTEM_OVERVIEW_AND_ROADMAP.md`
