# Insania Backend (Laravel 10)

The server behind the staff app for **Al Insania Charity Foundation Inc.**

It was started from the old school-management system's Laravel setup: the same infrastructure (Sanctum tokens, role middleware, one JSON response format, login throttling, security headers, unsafe-upload blocking, API keys), with the **school domain replaced by the foundation's own**: children and guardians, documents, bank accounts, sponsors and monthly sponsorship payments, projects, departments, staff and family accounts.

**Status:** 59 tests, 461 assertions, all passing. Run end to end over real HTTP with seeded demo data.

## Run it

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env (MySQL/MariaDB), or use SQLite: DB_CONNECTION=sqlite and create database/database.sqlite
php artisan migrate --seed          # demo data, local only
php artisan serve                   # http://127.0.0.1:8000/api
php artisan test
```

On a real server, never seed. Create the first System Admin instead (asks for the password, so it stays out of shell history):

```bash
php artisan migrate --force
php artisan insania:create-system-admin "Full Name" you@example.org
```

**Demo accounts** (local only; every one uses the password `DemoPass#2026`; the seeder refuses to run in production):

| Role | Email |
|---|---|
| System Admin | system@insania.test |
| Admin Maintenance | maintenance@insania.test |
| Security Admin | security@insania.test |
| President Admin | president@insania.test |
| Staff, Aytam | aytam@insania.test |
| Staff, Finance | finance@insania.test |
| Staff, Projects | projects@insania.test |
| User (family) | family@insania.test |

## Roles and permissions

Defined in one file, `config/roles.php`, and checked on every route before any controller runs (`permission:` middleware). Technical roles can never see a child's record or money details; a test fails if that ever changes.

| Role | Can do | Cannot do |
|---|---|---|
| **System Admin** | system health, system settings | children, documents, money |
| **Admin Maintenance** | system health | any personal data |
| **Security Admin** | create users, assign roles and departments, reset passwords, end sessions, read the audit log | edit records; promote anyone to System Admin; change their own role |
| **President Admin** | every office feature: children, documents, bank verification, payments, projects, reports | system settings, user accounts |
| **Staff** | only their department: **Aytam** = children, documents, bank verification; **Finance** = payments, reports; **Projects** = projects. Relief, Teaching, HR and Administration have none yet | anything outside their department |
| **User (family)** | see their own family's children and document status | other families, bank or money details |

A System Admin account can only be created from the command line, never through the API.

## API

All routes are under `/api`, return one envelope (`{success, data, meta}` or `{success:false, code, message}`), and need `Authorization: Bearer <token>` except `login`.

| | Route | Needs |
|---|---|---|
| POST | `/login`, `/logout` · GET `/me` · GET/PATCH `/me/preferences` · GET `/dashboard` | any account |
| GET | `/children` (search `q`, `pay_type`, `status`, paging) · `/children/{id}` | children.view |
| POST/PATCH | `/children`, `/children/{id}` | children.create / update |
| PUT | `/children/{id}/bank/verification` | bank.verify |
| GET/POST | `/children/{id}/documents` · GET `/…/documents/{type}/download` | documents.view / upload |
| GET | `/payments?month=YYYY-MM&scope=all\|bank\|cash` | payments.view |
| POST | `/payments/{child}/mark-cash` | payments.mark_cash |
| GET | `/reports/summary` · `/reports/payments.csv` | reports.view / export |
| GET/POST/PATCH | `/projects`, `/projects/{id}` | projects.view / manage |
| GET/POST/PATCH | `/users`, `/users/{id}` · POST `/users/{id}/password`, `/users/{id}/revoke-tokens` | users.manage |
| GET | `/audit` | audit.read |
| GET | `/system/health` · GET/PATCH `/system/settings` | system.health / settings |
| GET | `/my/family` | family.view_own |
| GET | `/partner/summary?month=` | an **API key** (`X-Api-Key`), totals only, no names |

Issue a partner key (shown once; only its hash is stored): `php artisan insania:create-api-key "Dar Al Ber" 1`

## How the old system was used

| Old system | Here |
|---|---|
| Laravel app, Sanctum tokens, `EnsureRole`, `ApiResponse`, `ApiExceptions`, `LoginThrottle`, `SecurityHeaders`, `BlockUnsafeUploads`, `ApiKeyAuth` | **Kept**, with the upload blocker and exceptions answering in the same JSON format |
| `schools` + `school_id` on every table | `foundations` + `foundation_id`, enforced by a scope that fails closed (a signed-in account with no foundation sees nothing) |
| `orphan_profiles`, `sponsors`, `orphan_sponsorships`, `sponsorship_payments`, `user_documents` | `children` (with guardians), `sponsors`, `sponsorships`, monthly `sponsorship_payments`, typed private `child_documents` |
| Numeric `role_id` and one middleware class per role | A role name plus permissions in `config/roles.php`. The old role list was reconstructed by guesswork and is not used |
| Permission catalog of school capabilities | Rewritten for the foundation |
| Hard-coded install check, school Blade views, curricula, grades, schedules, scholarships, gate/RFID attendance, library, transport | **Left out**: school-only. The original zips still have them |

## Security notes

- Passwords: at least 12 characters with mixed case and a number. Sign-in tokens expire after 12 hours (`SANCTUM_EXPIRATION`). Five wrong sign-ins lock that account for a minute; wrong password and unknown email give the same answer.
- Changing someone's access or password ends their sessions.
- Uploads: PDF or JPG/PNG/WebP up to 5 MB, content checked, stored privately under random names, never served directly. Downloads and uploads are audited.
- Bank details: only the last four digits are stored.
- CSV exports neutralise spreadsheet formulas.
- The audit log holds ids and short labels, never names, account numbers or documents.
- Set `CORS_ALLOWED_ORIGINS` to the staff app's real address in production (it defaults to `*`). Keep `APP_DEBUG=false`.
- Mail settings: the old system's `config.json` listed mail credentials. If real ones were ever in a shared file, change them.

## Not done yet

- The staff app prototype still uses its own fictional data. Connecting it means replacing that with calls to this API.
- PDF reports (CSV works), backups and restore, an install wizard, email sending, Aytam visit reports, teachers and relief goods modules.
