# Foundation Management System

An offline-first web application for an Islamic association / foundation. This repository is a
**separate application** with its own database, users, configuration and storage. It shares no code,
tables or credentials with any other system.

**Status — Phase 1 (backend) and Phase 2 (web client).** Installer, authentication, roles & permissions,
foundation profile, settings, places, audit log, device identity and the synchronization API are built and
tested, and a browser client works on top of them: it keeps its own local database (IndexedDB), saves every
change on the device first, syncs in the background, and shows what is waiting, failed or in conflict. It is
a plain website for now; installing it as an app (service worker, manifest) is a later phase. Aytam, beneficiaries, relief,
donations, projects, inventory and volunteers are intentionally **not** built yet — the architecture is
ready for them (see `docs/ARCHITECTURE.md`).

## Deploy (no command line needed)

1. Upload the application, with `vendor/` installed (`composer install --no-dev -o`), to a PHP ≥ 8.3 host.
   Point the web root at `public/`.
2. Open the site's URL. You are taken to the setup wizard (`/install`).
3. Read the one-time token from `storage/app/install/token` (File Manager / FTP) and paste it in.
   (Not needed when you browse from the server itself on `127.0.0.1`.)
4. Follow the wizard: requirements → database → system → foundation → administrator → device → install.

The wizard supports MySQL/MariaDB or SQLite (single-device installs), needs no internet, writes `.env`
for you, builds the database, and then **locks itself**: `/install` answers *"This system is already
installed."* The administrator chooses their own password; there are no default accounts.

If the lock file, `.env` or `APP_KEY` go missing or disagree, the app shows a recovery screen and
refuses to reinstall. Nothing is reset or deleted automatically.

## Operate

```bash
php artisan foundation:status    # install state; checks lock, .env and database agree (read-only)
php artisan foundation:upgrade   # after deploying a new release: migrations + new settings/permissions
```

Logs: `storage/logs/install.log` (installation) and the normal Laravel log.

## Develop

```bash
composer install
php artisan key:generate         # developer machines only; the wizard handles servers
vendor/bin/phpunit               # SQLite in memory; no services required
php artisan serve                # then open /install
```

## Documents

- `docs/ARCHITECTURE.md` — design decisions, data model, security, extension points.
- `docs/SYNC_PROTOCOL.md` — the exact push / pull / status / conflict contract the client builds against.
- `docs/CLIENT.md` — how the browser client is built, how to add a screen or an entity, and how it is tested.
