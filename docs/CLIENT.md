# Browser client

A no-build-step web client served by Laravel (`resources/views/shell.blade.php` → `public/app/main.js`).
Plain ES modules, one small vendored library (Dexie, for IndexedDB), the device's own system font, a strict
Content-Security-Policy (no inline scripts or styles). It works from a domain root or a sub-folder
(`/acr/`) because every URL is built from `<html data-base>`.

## Layout

| Path | Role |
| --- | --- |
| `public/app/core/` | `util` (ids, escaping `html` tag, dates), `api` (CSRF, device token, errors), `db` (Dexie), `entities` (client registry), `router` (hash routes), `ui` (toasts, sheets, forms), `theme` |
| `public/app/sync/outbox.js` | The only way screens write replicated data: `createRecord / updateRecord / removeRecord`. Writes the row **and** a queue entry in one transaction. |
| `public/app/sync/engine.js` | Push (batches of 50) → pull (atomic with the cursor) → conflicts. Phases: idle, syncing, offline, auth, device, error. |
| `public/app/auth/` | Session state machine, device PIN (PBKDF2, 5 tries), idle lock |
| `public/app/views/` | One module per screen, each exporting `{ mount(ctx) }` that returns a cleanup function |
| `public/css/tokens.css` | The design tokens (colour, type, spacing, radius, shadow, motion), light and dark. Shared with the installer. |
| `public/css/app.css` | Every component (sidebar, tab bar, cards, grouped lists, table, forms, switches, sheets, toasts, skeletons) built only from the tokens |

## Design system

Apple-inspired: neutral surfaces, one accent (blue), hairlines instead of shadows, system font (SF Pro on Apple
devices), generous spacing on a 4/8/12/16/20/24/32/40/48/64 scale, 8-20px radii. Colour is used for state only
(green ok, orange attention, red error, blue information). Desktop uses a quiet sidebar; phones get a top bar,
a bottom tab bar and bottom sheets. Lists are "grouped rows", People is a sortable table that turns into a list
on phones, destructive and secondary actions live in action sheets. Dark mode is a real theme, not an inversion.
Change a token in `tokens.css` and every screen follows; screens never carry their own styles.

## Rules the code follows

* **Local first.** Replicated data is written locally and queued; the server decides later. Screens read
  only from the local database (`kit.live(query, cb)` re-runs on any change).
* **Online-only actions are explicit**: create user / reset password (a password is made and hashed on the
  server), device registration and tokens, role permissions, logo upload, ending other sessions. Each says so.
* **Nothing is silently lost.** Sign-out with unsynced work asks first; a pull never overwrites a field with a
  queued edit; a different user signing in on the same browser is blocked until the first user's work is synced.
* **Secrets** (temporary passwords, device tokens) are shown once and never stored.
* **No inline handlers or styles**; output is escaped by the `html` tagged template unless wrapped in `raw()`.

## Adding a screen

1. Create `public/app/views/<name>.js` exporting `default { async mount(ctx) { …; return cleanup; } }`.
2. Add a line to `routes` in `core/router.js` (title, icon, `perm`).

## Adding an entity (a future module)

1. Register it on the server (`SyncRegistry`, see `docs/ARCHITECTURE.md`).
2. Call `registerEntity('name', { indexes, derive })` in `core/entities.js` before the database opens.
3. Read it with `db.<name>`; write it with `createRecord/updateRecord/removeRecord('<name>', …)`.

## Tests

* `tests/js` — `node --test`: the sync engine against a real PHP backend (19 tests).
* `tests/e2e` — Playwright in real Chromium against a real, freshly installed backend: offline edits and
  reconnect, two devices in conflict (keep server's / use mine), people / settings / roles / devices / PIN /
  sign-out guard. `cd tests/e2e && npm install && npm test`.

## Not built yet (deliberately)

Installable app (service worker, manifest, offline reload of the page itself), at-rest encryption of the
local database, upstream server-to-server sync, change-feed compaction.
