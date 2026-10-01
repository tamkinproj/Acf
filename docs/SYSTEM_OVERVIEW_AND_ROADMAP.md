# Foundation Management System — Overview and Roadmap

*A portal for a charitable foundation: orphan care (Aytam), teaching, relief goods, projects (Mashru'), and reporting to partners such as Dar Al Ber (Dubai).*

> **How to read this document.** Everything marked ✅ is built and tested. Everything marked 🧭 is a **proposal** for the next phases. It is meant to be corrected by the foundation before anything is built.

---

## 1. What this system is

A web application that a foundation can run on ordinary shared hosting (it is installed on Hostinger using only File Manager). Its purpose is to give the foundation **one trustworthy place** for its people, its places of work, and — step by step — its programs.

Its three defining ideas:

1. **Offline-first.** Field staff often have poor signal. Every change is saved on the phone first and sent to the server later. Nothing is lost when the connection drops.
2. **Nothing changes silently.** If two people edit the same record, nobody is overwritten; the system shows both versions and a manager decides. Every action is written to a permanent activity log.
3. **Built to grow.** The foundation core is finished; each program (Aytam, teaching, relief, projects) is a *module* that plugs into it without rebuilding anything.

It is **separate from MuslimEdu**: own database, own users, own folder, own cookies.

---

## 2. Where we are today

### ✅ Built (Phase 1 and 2)

| Area | What works |
|---|---|
| **Installer** | A setup wizard that works with no internet: checks the server, connects the database, creates the Super Admin with a password you choose, registers the first device. Never shows database passwords. Refuses to install over an existing database. |
| **Sign-in and security** | Email + password, session cookies, rate limiting, forced password change for temporary passwords, optional 6-digit device PIN, automatic lock after idle time, sign-out protection when work has not synced. |
| **People and roles** | Six built-in roles and 16 permissions (see §4). Create people (a one-time temporary password is shown once), edit them, disable them, reset passwords. |
| **Foundation profile** | Name, logo, address, registration details. |
| **Places** | A tree: country → region → province → municipality → barangay → site. Works offline. |
| **Devices** | Every phone/browser that syncs is registered; access can be withdrawn if a phone is lost. |
| **Settings** | Time zone, language default, currency, idle-lock time, sync interval. |
| **Activity log** | Who did what, when, from which device. Cannot be edited. |
| **Offline sync** | Local database in the browser, a queue of changes, background sync, a Sync screen showing what is waiting / failed / in conflict, and conflict resolution (keep server's, use mine, or combine). |
| **Design** | A clean, calm, Apple-inspired interface: sidebar on desktop, tab bar and bottom sheets on phones, real dark mode. |
| **Recovery tools** | One-time pages for hosts without a command line: Super Admin password reset, session/cookie diagnostic. |

### 🧪 How it was tested

* 128 automated PHP tests (installer, sign-in, permissions, sync, conflicts, upgrade).
* 19 sync-engine tests against a real backend.
* Real-browser (Chromium) flows: add places offline then reconnect; two devices editing the same record and resolving the conflict; people / settings / roles / devices / PIN; session loss; wrong cookie path.

### ⚠️ Known issues and not-yet-done

* **Sign-in on the current Hostinger site:** the URL entered during setup was `…/acf` but the site lives at `…/acr`, so the browser drops the login cookie. *Workaround:* in `foundation_app/.env` set `APP_URL=https://manhaje.com/acr` and `SESSION_PATH=/acr`. *Permanent fix:* the code now takes the cookie path from the real folder; it is written and browser-tested but still needs to be packaged and uploaded.
* The interface is **English only** for now. The language setting exists and the layout is ready for right-to-left, but Arabic (and Filipino) translations are not written.
* **Not installable as a phone app yet** (no home-screen icon, and the page itself needs a connection to load the first time).
* No backup/restore screen yet; back up the database from hPanel.
* The local copy of data inside the browser is not encrypted at rest (it is protected by the PIN lock and sign-out wipe).

---

## 3. How the system works (simple version)

```
 Phone / computer (browser)                     Server (Hostinger)
 ┌────────────────────────────┐                ┌───────────────────────────┐
 │ Screens (what you see)     │                │ Laravel application       │
 │ Local database (IndexedDB) │  ── sync ──▶   │ MySQL database            │
 │ Queue of unsent changes    │  ◀── sync ──   │ Rules, permissions, audit │
 └────────────────────────────┘                └───────────────────────────┘
        works with no signal                      the single source of truth
```

* Reading and writing always happen against the **local** database, so the app is fast and works offline.
* A background **sync** sends the queue and receives other people's changes. Each device has its own **device token**, so changes can be traced and a lost phone can be cut off.
* The server **re-checks every change** (permissions and rules), so a modified phone cannot do what its role does not allow.
* Technology: Laravel (PHP 8.3+), MySQL/MariaDB (SQLite possible), plain JavaScript in the browser. No build tools and no Composer needed on the host.

**Adding a program later** means: describe its records on the server, register them on the client, add permissions and screens. The sync, offline queue, conflicts, audit log and roles are reused as they are.

---

## 4. Roles and permissions (today)

Each person has **exactly one role**. A role is a list of permissions; the Super Admin can switch each on or off.

| Role | Intended for | Can do today |
|---|---|---|
| **Super Admin** | Owner / head (1–2 people) | Everything, including editing roles. Locked: cannot be changed. |
| **Foundation Admin** | Day-to-day manager | Everything except editing roles: people, settings, foundation profile, places, devices, activity log, conflict resolution. |
| **Staff** | Office workers | View Home / Foundation / Settings / Places; add, edit, remove places; resolve conflicts. |
| **Field Worker** | People in the field | View Home / Foundation / Settings / Places; sync their phone. Read-only for now. |
| **Volunteer** | Limited helpers | Same as Field Worker for now. |
| **Viewer** | Board members, observers | Same as Field Worker for now. |

Safety rules: nobody can change their own role or disable themselves; the last Super Admin cannot be removed; disabling a person signs them out everywhere; every change is logged.

*Field Worker, Volunteer and Viewer are identical until the program modules add their own permissions.*

---

## 5. Roadmap 🧭

### Order and why

| # | Module | Why in this position |
|---|---|---|
| **3** | **Aytam (orphan care)** | The foundation's core mission and the most sensitive data; it shapes roles, privacy rules and sync scoping that every later module reuses. |
| 4 | Teachers & teaching | Students largely come from the same people; needs the Aytam person records. |
| 5 | Relief goods | Stock and distributions; needs places and (optionally) beneficiary households. |
| 6 | Projects (Mashru') | Budgets and activities; links Aytam, teaching and relief to a purpose and a budget. |
| 7 | Donations & partner reporting (Dar Al Ber) | Needs everything above to report on; builds the evidence partners ask for. |

Each module follows the same recipe, so it can be delivered, tested and used on its own.

### Phase 3 — Aytam (orphan care) 🧭

**Records (proposed):**

* **Orphan profile** — name, date of birth, sex, photo (with consent), place, family situation, status.
* **Guardian / caretaker** — who is responsible, relationship, contact details.
* **Documents** — birth certificate, death certificate of parent(s), school records (stored privately).
* **Education** — school, grade, results, attendance notes.
* **Health notes** — restricted to a few roles.
* **Visits and follow-ups** — dated notes by the field worker, next-visit reminders.
* **Sponsorship** — which sponsor supports which child, amount, period, payment status (links to Phase 7).
* **Assignment** — which field worker is responsible for which child.

**A simple status flow:** `Applied → Verified → Approved → Active → Graduated / Left the program / Deceased`. Approving is a deliberate step by a supervisor.

**Proposed permissions:** view orphans · add/edit orphans · approve orphans · view sensitive details (health, documents) · view sponsorship/money · add visit notes · view Aytam reports.

**Proposed new role — Aytam Supervisor (Mushrif):** sits between Foundation Admin and Staff. Sees all orphan files, approves enrollments and sponsorship changes, assigns children to workers and reviews visit reports. Does **not** manage users, roles, devices or system settings. *(Question: program supervisor, or the supervisor who lives with the children at the orphanage? These may need two roles.)*

**Child-protection principles (to be agreed):**

* Least access: most roles see only what they need; Viewer/Volunteer see no private details by default.
* **Field phones only receive the children assigned to them**, not the whole register — a lost phone then exposes little. (This needs a small extension to sync: scoping by assignment.)
* Photos and documents only with recorded consent; access to sensitive files is logged.
* Nothing is ever hard-deleted; records are archived, with history kept.

### Phase 4 — Teachers & teaching 🧭

Teachers (contracts, qualifications, stipend/salary records) · classes and halaqat (Quran, Arabic, school subjects) · enrolment of students (orphans and community children) · attendance by class and date (offline-friendly) · schedules · progress (memorisation, grades, reports) · teacher evaluation notes.
Roles: *Teacher* (their own classes only), *Academic Coordinator* (all classes).

### Phase 5 — Relief goods 🧭

Items and units · warehouses/storage points · stock in/out with reasons and receipts · packages/kits · distribution events tied to a **place** and (optionally) a project · recipients (households) with receipt confirmation, even offline · low-stock and expiry alerts · full audit of where every item went.
Roles: *Warehouse Keeper*, *Distribution Officer*.

### Phase 6 — Projects (Mashru') 🧭

Project record (goal, place, dates) · budget lines and spending · activities and milestones · link to Aytam, relief and teaching activity · documents and photos · progress and completion reports.
Roles: *Project Manager*, *Finance Officer*.

### Phase 7 — Donations and Dar Al Ber / partner reporting 🧭

Donors and partners · donations and campaigns · receipts · **restricted funds** (money given for a specific purpose is tracked to that purpose) · sponsorship payments · reports a partner can read: sponsored children (privacy-safe), distributions, project spending, impact summaries — exportable as PDF/Excel.
A **Partner (read-only)** role that sees reports, never private child details.
*Nothing has been built or assumed about a Dar Al Ber interface; we need to know what they require (report format, frequency, whether they need direct access).*

### Cross-cutting improvements 🧭

| Item | Why |
|---|---|
| Arabic (RTL) and Filipino translations | Staff and partners read in different languages |
| Installable phone app + offline page loading | Home-screen icon; start the app with no signal |
| Reports and exports (PDF / Excel) | Partner reporting, board packs |
| Import from Excel | Bring in the existing registers |
| Backup & restore screen | Safety net without hPanel know-how |
| "Run update" button | Upgrades without File Manager juggling |
| Notifications (e.g. next visit due) | Follow-ups do not get forgotten |
| Encrypted local storage on field phones | Extra protection for lost devices |
| Custom roles in the app | Create *Mushrif*, *Teacher*, *Warehouse Keeper*, *Partner* without a developer |

---

## 6. Decisions the foundation needs to make

1. Which program hurts most today — orphans, teaching, relief goods or projects? (This sets the order above.)
2. Aytam: is sponsorship **per child** (a sponsor funds one child), or **general support**?
3. Who may **approve** a new orphan or a sponsorship change: the Mushrif, or the Foundation Admin?
4. Which staff work offline in the field, and which work in an office?
5. What does **Dar Al Ber** require: a report template, a schedule, or direct access?
6. Languages needed on screen: English only, or Arabic / Filipino as well?
7. Are there existing registers (Excel, paper) to import?
8. Data protection: are there legal or donor requirements about child photos and records?

---

## 7. Glossary

* **Aytam** (أيتام) — orphans; the orphan-care program.
* **Mushrif** (مشرف) — supervisor.
* **Mashru'** (مشروع) — project.
* **Dar Al Ber** (دار البر) — the Dubai charity partner connected to the foundation.
* **Halaqah** (حلقة) — a study circle or class.
* **Device token** — the secret that identifies a registered phone or browser to the server.
* **Sync** — sending a device's saved changes to the server and receiving others' changes.
* **Conflict** — two people changed the same record before syncing; a manager chooses the result.
* **Offline-first** — the app works fully without a connection and catches up later.

---

*Document version 1.0 · describes the system at version 1.0.0.*
