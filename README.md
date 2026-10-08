# IT Profile — s0sta

Production-ready **management systems for real businesses** — IT operations and field service — designed and built from scratch.

Plain **PHP 8 + PDO** (SQLite *or* MySQL) with vanilla JS/CSS — no frameworks, no composer, no build step. Every system installs through a web installer and runs on ordinary shared hosting.

---

## Projects

### 🎫 Daem — IT Help Desk

**A complete help desk for IT teams.** Requesters submit tickets, agents work a queue with SLA targets, managers watch live dashboards and reports.

| | |
|---|---|
| **Purpose** | Incident and service-request management for an IT department |
| **Highlights** | Tickets with categories, priorities and **SLA targets** (first-response and resolution tracking, breach warnings) · internal notes vs. public replies · automatic reopen when the requester answers a solved ticket · knowledge base with live suggestions while typing a ticket · file attachments · dashboards, agent workload and 14-day volume charts · reports with **SLA-compliance %** and CSV export · full audit log |
| **Roles** | Administrator · Agent · Requester |
| **Languages** | English · **Deutsch** · **العربية (RTL)** |
| **Stack** | PHP 8 · PDO (SQLite demo / MySQL production) · vanilla JS/CSS |
| **Docs** | [Project README](daem-helpdesk/README.md) · [deployment guide](daem-helpdesk/UPLOAD-INSTRUCTIONS.txt) |
| **Tests** | `tests/smoke.sh` (39 checks) · `tests/i18n-smoke.sh` (43 checks) · `diag.php?go=1` self-check |

```bash
cd daem-helpdesk
php -S localhost:8000        # open http://localhost:8000/install.php  (choose SQLite)
php seed.php                 # optional demo data
```

---

### 🏛 Idara — Manager Workspace

**إدارة** — the daily work space of a department manager: his own tasks, his team's tasks, and the approvals that land on his desk.

| | |
|---|---|
| **Purpose** | Task management, team follow-up, multi-step approval workflow, correspondence, meetings and reports — built for a government/institutional setting (Investment Authority departments in the demo data) |
| **Highlights** | Own tasks with **checklists that update progress automatically** · team workload monitoring · **approval chains** (approve / return for revision / reject, with mandatory notes) · **delegation** during absence · official correspondence register with due dates · meetings with attendance and minutes · deadline calendar · reports with CSV export · full audit log |
| **Roles** | System Administrator · Executive Leadership · Department Manager · Employee — with row-level visibility (a manager sees his team and department, an employee only his own work) |
| **Languages** | **Arabic (RTL, default)** + English, with a beginner guide at `?p=doc` in very simple language |
| **Stack** | PHP 8 · PDO (SQLite demo / MySQL production) · vanilla JS/CSS |
| **Docs** | [Project README](idara-manager/README.md) · [deployment guide](idara-manager/UPLOAD-INSTRUCTIONS.txt) |
| **Tests** | `tests/smoke.sh` (end-to-end) · `tests/i18n-smoke.sh` (bilingual) · `diag.php?go=1` self-check |

```bash
cd idara-manager
php -S localhost:8000        # open http://localhost:8000/install.php  (choose SQLite)
php seed.php                 # optional demo organisation
```

Demo sign-ins: `admin / Admin@1234` · `sarah.qahtani / Manager@1234` (department manager) · `noura.shammari / User@1234` (employee).

---

### ◆ Sanad — Field Service & Job Management

**سند** — the daily work organiser for a field-service company (A/C, plumbing, electrical, cleaning, pest control…): dispatch, jobs, stock and billing in one place.

| | |
|---|---|
| **Purpose** | Job scheduling and dispatch for businesses that send technicians to customers — replaces paper and WhatsApp groups |
| **Highlights** | **Dispatch board** with one column per technician plus an “Not assigned” column · work orders with status workflow, priority and time windows · per-job **checklists**, progress notes and photo attachments · parts stock with **automatic deduction** on use and low-stock alerts · **AMC maintenance contracts** whose visits are planned automatically and become jobs in one click · **VAT invoices** built from a finished job (labour + parts) with payment recording and a printable A4 sheet · reports with CSV export · notifications and full audit log |
| **Roles** | Administrator · Dispatcher · Technician (sees only their own jobs) · Accountant |
| **Languages** | English · **Deutsch** · **العربية (RTL)** |
| **Stack** | PHP 8 · PDO (SQLite demo / MySQL production) · vanilla JS/CSS |
| **Docs** | [Project README](sanad-field-service/README.md) · [deployment guide](sanad-field-service/UPLOAD-INSTRUCTIONS.txt) |
| **Tests** | `tests/smoke.sh` (**89 checks**) · `tests/i18n-smoke.sh` (**42 checks**) · `diag.php?go=1` self-check |

```bash
cd sanad-field-service
php -S localhost:8000        # open http://localhost:8000/install.php  (choose SQLite)
php seed.php                 # optional demo data
```

Demo sign-ins: `noura.dispatch / Demo@1234` (dispatcher) · `ahmed.tech / Demo@1234` (technician) · `rana.accounts / Demo@1234` (accountant).

---

## What the systems have in common

- **Zero dependencies** — no composer, no node, no build step; upload the folder and run.
- **Web installer** — choose SQLite (instant demo) or MySQL/MariaDB (production), create the first administrator, and self-repair a broken configuration (`install.php?reconfigure=1`).
- **Security by default** — PDO prepared statements everywhere, `password_hash`, CSRF tokens on every form, login throttling, hardened sessions, upload whitelist with size limits, `.htaccess` blocking of `data/`, `storage/`, `includes/`, `sql/`, `lang/`.
- **Accountability** — an audit log records every action (who, what, when, from which IP).
- **Operations ready** — in-app notifications, CSV exports, `diag.php?go=1` deployment self-check, `integrity.php` release manifest and shell test suites.
- **Multilingual** — right-to-left layout support, Arabic + English (Daem, Idara) and full English/German/Arabic parity (Sanad), plus an A1-level guide inside the app for non-technical staff.

---

## Repository layout

```
.
├── daem-helpdesk/           IT help desk (tickets · SLA · knowledge base · reports · 3 languages)
│   └── ...
├── idara-manager/           Manager workspace (tasks · team · approvals · correspondence · meetings)
│   └── ...
└── sanad-field-service/     Field service (dispatch board · jobs · contracts · stock · invoices)
    ├── index.php            front controller (routes + role guards)
    ├── install.php          web installer (SQLite / MySQL) — delete after installing
    ├── seed.php             demo service company for testing
    ├── diag.php             deployment self-check (delete in production)
    ├── includes/            core: bootstrap, db, auth, i18n, helpers, models, layout
    ├── pages/               one file per route (+ pages/admin/)
    ├── lang/                English master + German/Arabic UI and documentation content
    ├── sql/                 schema for SQLite and MySQL/MariaDB
    ├── assets/              CSS (light/dark, RTL) and vanilla JS
    └── tests/               end-to-end and trilingual test suites
```

---

© 2026 s0sta — all rights reserved.
