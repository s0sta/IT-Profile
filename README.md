# IT Profile — s0sta

Production-ready **IT management systems**, designed and built from scratch.

Plain **PHP 8 + PDO** (SQLite *or* MySQL) with vanilla JS/CSS — no frameworks, no composer, no build step. Every system installs through a web installer and runs on ordinary shared hosting.

---

## Projects

### 🏛 Idara — Manager Workspace

**إدارة** — the daily work space of a department manager: his own tasks, his team's tasks, and the approvals that land on his desk.

| | |
|---|---|
| **Purpose** | Task management, team follow-up, multi-step approval workflow, official correspondence, meetings with minutes, deadlines calendar and reports — built for a government/institutional setting (Investment Authority departments in the demo data) |
| **Highlights** | Own tasks with checklists that update progress automatically · team workload view · **approval chains** (approve / return for revision / reject, with mandatory notes) · delegation during absence · correspondence register with due dates · meetings, attendance and minutes · deadline calendar · reports with CSV export · full audit log |
| **Roles** | System Administrator · Executive Leadership · Department Manager · Employee — with row-level visibility (a manager sees his team and department, an employee only his own work) |
| **Languages** | **Arabic (RTL, default)** + English — with a beginner guide at `?p=doc` written in very simple language |
| **Stack** | PHP 8 · PDO (SQLite demo / MySQL production) · vanilla JS/CSS |
| **Docs** | [Project README](idara-manager/README.md) · [deployment guide](idara-manager/UPLOAD-INSTRUCTIONS.txt) |
| **Tests** | `tests/smoke.sh` (end-to-end) · `tests/i18n-smoke.sh` (bilingual) · `diag.php?go=1` self-check · `integrity.php` release manifest |

```bash
cd idara-manager
php -S localhost:8000        # open http://localhost:8000/install.php  (choose SQLite)
php seed.php                 # optional demo organisation
```

Demo sign-ins: `admin / Admin@1234` · `sarah.qahtani / Manager@1234` (department manager) · `noura.shammari / User@1234` (employee).

---

## What the systems have in common

- **Zero dependencies** — no composer, no node, no build step; upload and run.
- **Web installer** — choose SQLite (instant demo) or MySQL/MariaDB (production), create the first administrator, self-repairs a broken configuration (`install.php?reconfigure=1`).
- **Security by default** — PDO prepared statements everywhere, `password_hash`, CSRF tokens on every form, login throttling, hardened sessions, upload whitelist with size limits, `.htaccess` blocking of `data/`, `storage/`, `includes/`, `sql/`, `lang/`.
- **Accountability** — an audit log records every action (who, what, when, from which IP).
- **Operations ready** — in-app notifications, CSV exports, `diag.php?go=1` deployment self-check, `integrity.php` release manifest, and shell test suites.
- **Arabic-first, bilingual** — right-to-left layout, Arabic master language file and English translation, with an A1-level guide inside the app for non-technical staff.

---

## Repository layout

```
.
└── idara-manager/           Manager workspace (tasks · team · approvals · correspondence · meetings)
    ├── index.php            front controller (routes + role guards)
    ├── install.php          web installer (SQLite / MySQL) — delete after installing
    ├── seed.php             demo organisation for testing
    ├── diag.php             deployment self-check (delete in production)
    ├── includes/            core: bootstrap, db, auth, i18n, helpers, models, layout
    ├── pages/               one file per route (+ pages/admin/)
    ├── lang/                Arabic master + English UI and documentation content
    ├── sql/                 schema for SQLite and MySQL/MariaDB
    ├── assets/              CSS (light/dark, RTL) and vanilla JS
    └── tests/               end-to-end and bilingual test suites
```

---

© 2026 s0sta — all rights reserved.
