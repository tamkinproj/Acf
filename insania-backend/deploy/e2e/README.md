Browser checks used before each release (Playwright, Chromium). They expect the site at http://127.0.0.1:8090
(app in /app/, server in /server/) with the demo accounts from `DemoSeeder` (local copies only, never on the live site).

- `roles.js`   every demo account signs in; menus and blocked pages match the role matrix.
- `offline.js` offline reading and writing, reconnect, lost reply (no duplicates), refused change, ended session, admin screens online-only.

Run: `NODE_PATH=$(npm root -g) node roles.js` (reseed first for `offline.js`: `APP_ENV=local php artisan migrate:fresh --seed --force`, then wait a minute so the sign-in limiter clears).

- `wizard.js`  fresh install: first sign-in of System Admin, Security Admin and President opens the checklist.
- `updates.js` signed patches: refusals, failing patch restores itself, apply, undo (needs patches made with `deploy/make-patch.py` and a throwaway test key; never the real key).
