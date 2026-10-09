# ◈ Daem — IT Help Desk Platform

**A complete, production-ready help desk for IT teams.** Requesters submit tickets, agents work a queue with SLA targets, managers watch live dashboards and reports — all from one clean, modern interface with light/dark themes.

Zero frameworks, zero composer, zero build step: plain **PHP 8 + PDO** (SQLite *or* MySQL) with vanilla JS/CSS. Upload it and it runs.

---

## Features

### For requesters (role: user)
- Submit tickets with category, priority and description
- **Live knowledge-base suggestions** appear while typing the subject (ticket deflection)
- Track the full conversation, attach files (5 MB each), reply any time
- Replying to a resolved ticket **reopens it automatically**
- Close your own resolved tickets, vote 👍/👎 on KB articles

### For agents (role: agent)
- Work queue with filters (status, priority, category, assignee, search) and sorting
- Change status / assign / priority / category directly on the ticket
- **Internal notes** (visible to staff only) + public replies
- First-response tracking — replying to a *new* ticket moves it to *Open*
- In-app notifications (bell), full audit trail, CSV reports

### For administrators (role: admin)
- Manage users (create, edit, deactivate, reset passwords) and roles
- Ticket categories, KB categories & articles, SLA targets per priority
- **Dashboard**: open/overdue/unassigned tickets, 14-day volume chart, agent workload, activity feed
- **Reports**: date-range summary, resolution-time + **SLA compliance %**, CSV export
- **Audit log**: every action recorded with user, entity and IP

### Security built in
- PDO prepared statements everywhere (SQL-injection safe)
- `password_hash()` / `password_verify()`, min-8-char passwords
- Per-session CSRF tokens on every form
- **Login throttling** — 5 failed attempts / 15 min per username or IP
- **One-time passwords** — an administrator reset generates a random password that is shown once and **must be changed at the next sign-in** (the user is sent straight to the profile page until they do)
- XSS-escaped output, hardened session cookies (HttpOnly, SameSite, strict mode, 2-hour idle timeout)
- File uploads: extension whitelist, 5 MB cap, randomized stored names, auth-checked downloads
- **Duplicate protection** — two ticket categories or two KB categories can never share a name (a friendly message instead of a database error)
- Security headers + `.htaccess` that blocks `data/`, `storage/`, `includes/`, `sql/`, `lang/`, `diag.php`, `integrity.php`, `README.md`, `UPLOAD-INSTRUCTIONS.txt`, unsets `X-Powered-By` and sends HSTS
- **No blank error pages** — fatal errors are logged to `data/error.log` and shown as a styled page

---

## Quick start (local demo — SQLite, 2 minutes)

Requires PHP ≥ 8.1 with `pdo_sqlite`.

```bash
cd daem-helpdesk
php -S localhost:8000
```

1. Open **http://localhost:8000/install.php**
2. Choose **SQLite**, set the site name and your admin account → *Install Daem*
3. Open **http://localhost:8000/** and sign in as your admin
4. Load demo data (2 agents, 3 requesters, 12 tickets, 6 KB articles):
   ```bash
   php seed.php
   ```

### Demo accounts (after seeding)

| Role | Username | Password |
|---|---|---|
| Administrator | *your admin* (from installer) | *your password* |
| Agent | `omar.ali` · `fatima.noor` | `Agent@1234` |
| Requester | `khalid.salem` · `sara.ahmed` · `nasser.qahtani` | `User@1234` |

---

## Production deployment (Hostinger)

1. In **hPanel → Databases → MySQL Databases**, create a database + user, note the host (usually `localhost`), name, user, password.
2. Upload the whole folder to `public_html/daem` (File Manager or FTP).
3. Open `https://your-domain.com/daem/install.php`, choose **MySQL**, fill in the credentials → *Install Daem*.
4. **Delete `install.php`** from the server.
5. (Optional) Run the demo seeder once: `php seed.php` over SSH, then delete `seed.php`.
6. Sign in and configure: **Admin → SLA & Settings**, then create your users and categories.

The included `.htaccess` works on Hostinger (Apache/LiteSpeed) and already blocks direct access to data/storage/includes.

> MySQL vs SQLite: the application code is identical — only the schema file and the PDO connection differ. SQLite is for instant demos; use MySQL in production for concurrency.

---

## Default SLA targets (editable in Admin → SLA & Settings)

| Priority | First response | Resolution |
|---|---|---|
| Urgent | 1 h | 8 h |
| High | 4 h | 24 h |
| Medium | 8 h | 72 h |
| Low | 24 h | 120 h |

A ticket whose first response passes its target is flagged ⏰ in every list and counted on the dashboard.

---

## Project structure

```
daem-helpdesk/
├── index.php            # front controller (router + authorization)
├── install.php          # web installer (SQLite/MySQL) — delete after install
├── seed.php             # optional demo data — delete after seeding
├── .htaccess            # hardening + static caching
├── data/config.php      # created by the installer (never commit)
├── sql/                 # schema.sqlite.sql · schema.mysql.sql
├── storage/uploads/     # ticket attachments (randomized names)
├── includes/
│   ├── bootstrap.php    # config, session hardening, boot
│   ├── db.php           # PDO wrapper (dual driver)
│   ├── auth.php         # roles, login throttling, session TTL
│   ├── helpers.php      # CSRF, SLA math, audit, notify, uploads
│   ├── models.php       # Users/Tickets/Replies/Kb/Notifications/AuditLog
│   └── layout.php       # shared UI (sidebar, topbar, badges, tables)
├── pages/               # one file per route (dashboard, tickets, kb, admin…)
└── assets/              # css/style.css · js/app.js (no dependencies)
```

---

## Languages — English · Deutsch · العربية

The whole interface is available in **three languages**, switchable at any time from the switcher in the top bar (and on the sign-in page):

| Language | Code | Direction | Notes |
|---|---|---|---|
| English | `en` | LTR | Master language file (`lang/en.php`) |
| German | `de` | LTR | Full translation, formal "Sie" |
| Arabic | `ar` | **RTL** | Full translation, right-to-left layout |

- Texts live in `lang/<code>.php` (UI) and `lang/doc-<code>.php` (documentation page). Missing keys fall back to English automatically.
- The chosen language is stored in the session **and** a `daem_lang` cookie, so it survives the next visit.
- **Beginner guide**: page `?p=doc` is public (no login needed) and explains the whole program in very simple A1 language — what a ticket is, the three roles, how to write and answer a ticket, what the statuses and priorities mean, SLA, the knowledge base, plus guides for agents and administrators and a ticket-lifecycle diagram.

**Adding another language:** copy `lang/en.php` to `lang/xx.php`, translate the values (keep the keys and `{placeholders}`), do the same for `lang/doc-en.php`, then add one line to the `DAEM_LANGS` array in `includes/i18n.php`.

## Database & documentation checks

- `tests/smoke.sh` — 39 end-to-end checks (English, all roles, security negatives).
- `tests/i18n-smoke.sh` — 43 checks across English / German / Arabic (page rendering, RTL layout, translations present, no English leftovers).
- `diag.php?go=1` — deployment self-check (file integrity, database, sessions, writable folders). Delete it in production.
- `tests/make-integrity.php` — regenerates `integrity.php` after any file change.

## Real-world workflows it supports

- **Incident management** — urgent production issue → SLA target → first-response tracking → resolution.
- **Service requests** — new laptop, access, software installs as tickets with categories.
- **Knowledge-centered support** — deflection via KB suggestions while typing, articles with helpful ratings.
- **Manager oversight** — workload per agent, SLA compliance, CSV exports for management review.
- **Accountability** — immutable-style audit log of every action.

## Roadmap ideas

Email notifications (SMTP), satisfaction surveys on resolution, asset lookup from a ticket, SLA business-hours calendar, more languages, REST API keys for external tools.

---

© 2026 s0sta — All rights reserved.
