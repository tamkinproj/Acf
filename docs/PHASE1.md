# Phase 1 — Platform & Foundation Core

The system is a multi-tenant SaaS: **Platform → Foundations → Users, Organizations, Programs, Data.**
Aytam is the first program built on it, not the system itself; further programs plug into the same core
(see [Adding a program](#adding-a-program)).

## Who can do what

| Role | Lives in | Can |
|---|---|---|
| **Platform Admin** | the platform (no foundation) | create / edit / suspend / deactivate foundations, manage their administrators, platform settings and other platform admins, see totals and platform activity. Cannot open any foundation's records. |
| **Foundation Admin** | one foundation | everything inside that foundation: profile, settings, users, roles, organizations, programs, program teams, activity. |
| **Mushrif** (Aytam program role) | one program | create/edit children, review registrations, families, guardians, documents, forms and links, partners, requirements, assign Field Workers. No foundation-wide users, roles or settings. |
| **Field Worker** (Aytam program role) | one program | only the children assigned to them, the documents and fields their role allows. No administration. |
| Custom roles | one foundation | any set of permissions the Foundation Admin picks. |

Access is by **permission**, never by role name. A person's foundation role gives foundation-wide permissions;
their role in a program (`program_user`) gives program permissions in that program only.

### Permissions

`foundation.view/update`, `users.view/create/update/deactivate`, `roles.view/manage`, `organizations.view/create/update`,
`programs.view/create/update/activate`, `settings.view/manage`, `audit.view`, `devices.view/manage`, `locations.view/manage`;
Aytam: `aytam.view/view_all/create/update/review/assign/import`, `documents.view/upload/verify`, `forms.view/create/update/publish`;
platform: `platform.view`, `platform.foundations.manage`, `platform.users.manage`, `platform.settings.manage`.
The catalogue lives in `app/Core/Access/PermissionCatalog.php`; a program module contributes its own.

## Multi-tenancy

* Every foundation-owned table has `foundation_id`. `TenantScope` (a global scope) filters every query by the signed-in
  user's foundation, `BelongsToFoundation` stamps new rows and refuses writes into another foundation, and the owner of a
  row can never change.
* The request's tenant comes from the **authenticated user**, never from a URL or header (`ResolveTenant`, middleware
  `tenancy:tenant|platform|any`). Route-model binding runs after it, so another foundation's id is simply a 404.
* Code that runs with no tenant (queue, console) fails closed: it sees nothing until it chooses a foundation explicitly
  (`TenantContext::runAs`, `asPlatform`, `asSystem`).
* Suspended or deactivated foundations lock their people out on the next request, not at next login.
* `tests/Feature/TenantIsolationTest.php` proves foundation A cannot read, write, list, search, export or guess its way
  into foundation B.

## Foundations (Platform Admin)

Create a foundation with its first administrator: the platform gives a temporary password **shown once**, which the
administrator must change at first sign-in. Provisioning creates the foundation's roles, settings, primary device record
and an audit entry. Status: Active / Inactive / Suspended (with a reason). Platform screens show **totals only**.

## Programs and organizations

A **program** belongs to a foundation and has a category (Aytam, Relief, Education, Healthcare, Food distribution,
Emergency assistance, Qurbani, Other) and a status (Draft → Active → Inactive → Archived). Categories that have a module
(today: Aytam) get that module's screens and rules; others are generic until their module is built. Program-specific
structure is never forced into another program's tables.

**Organizations** are a foundation-level master list (type, location, contacts). They are linked to programs; the
program-specific details (role, requirements, notes) live on the link, so one organization can serve several programs.

## Aytam

* **Master record** — permanent ID `AYT-000001` (a per-program sequence, never reused), personal / address / family /
  education / guardian details, status Draft → Pending review → Needs correction → Approved → Active → Inactive →
  Archived. Records are soft-deleted only.
* **Families and guardians** are reusable: several children share one family or guardian; edit once, shown everywhere.
* **Documents** — passport, birth certificate, diploma, transcript / Form 137, photo, medical certificate,
  police/NBI clearance, recommendation letter, other. Each has a status (Pending / Verified / Rejected / Expired), an
  expiry date, notes and a full version history (replacing a document adds a version; nothing is overwritten).
* **Field Workers** see and edit only the children assigned to them (`aytam_assignments`).

### Documents are stored privately

Files live on a private disk (`storage/app/private/documents`), never under the web root. Uploads are checked by content
(not by name): PDF, JPEG, PNG or WebP only, extension must match, images must fully decode, size limit
`FOUNDATION_DOCUMENT_MAX_KB` (default 10 MB). Stored names are random; the original name is kept as a sanitised label.
Downloads go through PHP, require permission and tenant, and are sent as attachments with `nosniff` and a sandbox CSP.
Storage is behind one class (`DocumentStorage`) so another disk (S3, etc.) is a configuration change.

## Registration

1. A Mushrif builds a form (`forms.*`): sections, questions (short/long text, number, date, dropdown, multiple choice,
   checkbox, yes/no, file, photo, address, phone, email), required flags, instructions, document requirements, reordering,
   preview. Every question maps to a field of the canonical Aytam model ("capture once, reuse everywhere") — forms never
   create their own schema.
2. **Publishing** freezes a numbered version of the form and yields a registration link `/apply/<token>`; unpublish or
   regenerate the link at any time. Submissions keep the version they were made against.
3. An applicant opens the link with no account, fills the form and uploads documents. Protection: honeypot, rate limits
   (60 views / 6 submissions a minute per IP), file validation. They receive a reference and a private status link.
4. The submission becomes **Pending review**. A reviewer (`aytam.review`) sees the answers, documents, missing items,
   validation problems and **possible duplicates**, and then approves (creating the permanent Aytam record, family,
   guardian and documents, and the AYT ID) or **sends back** with a correction request the applicant can answer.
   Every step is in the registration's history.

## Importing existing data

Aytam → Import: upload CSV or Excel → map columns (suggested from headings) → preview → validate → resolve duplicates →
import. Errors are shown **before** anything is written; date formats are never guessed (you choose ISO, day/month or
month/day). Rows that match existing records are flagged exact / probable / possible and the user decides per row (use
existing, create new, skip) — records are **never merged automatically**. The import runs in chunks and the uploaded data
is purged afterwards. Spreadsheet reading uses `openspout/openspout` (needs PHP `zip` and `xmlreader`).

## Duplicate detection

Name (normalised: case, spacing, punctuation, "bin/ibn/al" particles), date of birth and identifiers. Levels: exact,
probable, possible. Used by the registration review and by import. It only ever *suggests*.

## Audit log

Every important change is recorded with who, when, from which device, what changed (old → new values) and a summary:
users, roles, foundation, programs, organizations, children, families, guardians, documents (upload, verify, replace),
registrations (submit, approve, return), forms and links, imports, sign-ins and failures. Contact details, addresses,
free-text notes, passwords and link tokens are **excluded**; records are named so the log reads. Entries are immutable and
tenant-scoped; the platform keeps its own log for foundation lifecycle events.

## Offline-ready data

All new tables carry the sync metadata the existing engine uses: UUID primary keys, created/updated timestamps and users,
a version counter, soft delete and a change feed. The full offline workflows for Aytam are a later phase.

## Security checklist

Hashed passwords (bcrypt) and a password policy; HTTP-only same-site sessions; CSRF on the web forms; strict CSP (no
inline script/style); server-side authorization on every endpoint (the client only hides what you may not do);
tenant isolation as above; validation on every request; rate limits on sign-in, uploads and the public form; secrets only
in `.env`; unguessable ids; temporary passwords shown once; audit trail.

## Installation, upgrade, deployment

* **Fresh install** — `/install` wizard (requirements → database → system → platform administrator). It creates the
  *platform* and its first Platform Admin; foundations are created from the Platform screens afterwards.
* **Upgrade an existing (single-foundation) installation** — upload `update-foundation_app.zip` and `update-web.zip`
  (see `HOSTINGER.md`), open the site: it shows an upgrade page (`/upgrade`, one-time token in
  `storage/app/install/token`). The upgrade adopts your existing foundation as foundation #1 (all its data keeps its
  ids), converts the old Super Admin to Foundation Admin, adds the new tables and asks you to create the first Platform
  Admin. With a shell: `php artisan foundation:upgrade` then `php artisan platform:admin`.
* **Command line helpers** — `platform:admin`, `platform:foundation`, `foundation:status`, `foundation:upgrade`, and
  `foundation:demo` (clearly labelled demo foundation for evaluation only; never run it on production).
* **Environment** — the wizard writes `.env`. Optional: `FOUNDATION_DOCUMENT_MAX_KB` (document size limit, default 10240).
  MySQL/MariaDB or SQLite.
* **Tests** — `php artisan test` (backend, including tenant isolation and the full Phase-1 journey in
  `Phase1FlowTest`), `node --test tests/js/sync.test.mjs` and the Chromium scripts in `tests/e2e/`
  (`node tests/e2e/platform.mjs`, …).

## Adding a program

1. Implement `App\Programs\ProgramModule` (see `app/Modules/Aytam/AytamModule.php`): its category key, permissions,
   role templates, configuration rules.
2. Register it in `App\Programs\ProgramModules`.
3. Add its tables with `program_id` + `foundation_id` (use `BelongsToFoundation` and the sync traits).
4. Add its routes under `programs/{program}` guarded by `program:<key>,<permission>`, and a tab set in
   `public/app/views/program/workspace.js`.

The Foundation core — tenants, users, roles, organizations, programs, audit, storage — does not change.

## Not in Phase 1 (deliberately)

OCR / AI, partner portal, RFID, sponsorship and visit management, automated reporting, full offline sync of the new
records, billing / subscriptions, white-label, analytics. They are later phases of the roadmap
(`SYSTEM_OVERVIEW_AND_ROADMAP.md`).
