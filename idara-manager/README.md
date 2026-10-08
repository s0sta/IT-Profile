# ◈ Idara — Manager Workspace

**Idara is a manager workspace for a Saudi investment authority.** It puts three things in front of a manager every morning: **my tasks**, **my team's tasks**, and the **approvals that land on a manager's desk**. Tasks carry checklists, follow-ups and due dates; approvals move through a real multi-step chain with notes, delegation and a full history; correspondence, meetings with minutes, a calendar, reports and an audit log sit alongside them.

Zero frameworks, zero Composer, zero build step: plain **PHP 8 + PDO** (SQLite *or* MySQL) with vanilla JS/CSS. Upload it and it runs — the interface is Arabic by default (RTL) with a full English translation and a language switcher.

---

## Why a manager needs it

- **Approvals get lost in email.** A request sits in an inbox, nobody knows whose turn it is, and the decision is never recorded. Idara keeps an explicit, ordered chain and shows the current step.
- **Ownership is unclear.** "Who is doing this?" is answered by an assignee, a creator and a department on every task — and a manager sees their own team's work.
- **Overdue work is not followed up.** Due dates turn into red *overdue by n days* chips, appear on the dashboard and the calendar, and can be filtered in one click.
- **There is no audit trail.** Every create, update, decision, login and export is written to the audit log with user, entity, details, IP and time.
- **Work is spread across tools.** Tasks, approvals, letters, meetings, minutes and reports live in one place, in the same workspace and the same language.

---

## Features by role

### System Administrator (role: `admin`)

- Sees **everything**: all tasks, approvals, correspondence and meetings, across every department.
- **Administration panel** (admin-only routes):
  - **Users** — create, edit, assign role, department and direct manager, reset a password, activate/deactivate (an admin account and your own account can never be deactivated).
  - **Departments** — add/edit bilingual (Arabic/English) names, a code, a department manager; see user and task counts; toggle active.
  - **Task categories** and **Approval types** — bilingual reference data used by tasks and approval chains.
  - **Settings** — site name plus the four document prefixes (`task_prefix`, `approval_prefix`, `corr_in_prefix`, `corr_out_prefix`, 2–6 characters each).
  - **Audit log** — searchable record of every action.
  - **Reports** — date-range report over all tasks with summary cards, breakdowns and **CSV export**.
- May create a **delegation on behalf of any approver** and cancel any delegation.
- Uses the full manager workspace as well (my tasks, approvals, team, correspondence, meetings, calendar).

### Executive Leadership (role: `executive`)

- Same unrestricted **visibility** as an admin over tasks, approvals, correspondence and meetings — but without the administration panel.
- Typically the **final step** in approval chains (e.g. approvals that start with a department manager and end with the executive).
- **Team page** shows the full roster when the executive manages nobody directly, with each member's open and overdue counts.
- Can approve, reject or return any request that reaches them, add a note and attachments, and delegate authority while travelling.

### Department Manager (role: `manager`)

- The dashboard greets them as the **Manager Workspace**: my open tasks, **awaiting my decision**, my overdue tasks, tasks due this week, open letters, upcoming meetings, **team members** and **team overdue**.
- **Team's tasks**: a manager sees their own tasks, the tasks of their **direct reports**, and the tasks of their **own department** — with workload bars per member and open/overdue counts.
- **Approvals**: sees requests they raised, requests where they are an approver in the chain, and requests of their department; the **Inbox** tab lists exactly what awaits their decision right now.
- **Can manage tasks in scope** — change status, progress, assignee, priority, dates; reassign or delete what they created or manage.
- **Delegations**: delegate their approval authority to a colleague for a date range (with a reason) — while active, the delegate can decide on their behalf.

### Employee (role: `member`)

- Sees the tasks **assigned to them or created by them**, with all the working tools: status, progress, checklist, follow-up updates and file attachments.
- **Raises approval requests** (choose an approval type, priority, due date, an optional related task, and an ordered chain of approvers).
- **Correspondence** assigned to them and **meetings** they organise or attend.
- **Calendar** of their due tasks, approvals and meetings; **notifications**; personal **profile** with password change.
- Cannot see other people's approvals unless they are an approver in the chain or an active delegate.

---

## The approval workflow

An approval is a request plus an **ordered chain of approvers**.

1. **Create the request** (`?p=new-approval`): title, description, approval type, priority, due date, an optional link to a related task, and file attachments.
2. **Choose the chain** — pick the approvers in order (manager / executive / admin). Step 1 becomes `pending`; every later step waits as `waiting`.
3. **The current approver decides.** Three decisions are available:
   - **Approve** — the step is recorded as approved and the chain **advances** to the next waiting step; if there is no next step, the whole approval becomes **approved** and is closed.
   - **Reject** — the chain closes immediately; remaining steps are marked `skipped`.
   - **Return** — the chain closes as **returned** so the requester can revise and resubmit.
4. **Notes** — a note is optional on *approve* and **required** on *reject* and *return*. Files can be attached to the decision itself.
5. **Delegation** — if the assigned approver has an **active delegation** covering today, the delegate can open the request and decide; the page says explicitly that they are deciding on someone's behalf.
6. **History** — every step is shown as a chain with the approver, their role, the step status, their note and the decision timestamp. The current step is highlighted; after a reject/return the skipped steps stay visible.

**Statuses** — approval: `pending`, `approved`, `rejected`, `returned`. Step: `waiting`, `pending`, `approved`, `rejected`, `returned`, `skipped`.

The **Approvals** page has three tabs — **Inbox** (what awaits me, including delegations), **My requests**, and **All** — with filters for search, status, type, priority and sorting (updated / created / due / priority).

---

## Tasks explained

- **Reference** — every task gets a reference such as `TSK-2026-0001` from the configurable prefix.
- **Statuses** — `new`, `in_progress`, `waiting`, `blocked`, `completed`, `cancelled`. Completing a task sets progress to 100 % and stamps `completed_at`; marking it complete again offers **Reopen**.
- **Checklist with auto-progress** — add checklist items on the task page; ticking them recalculates progress as *done ÷ total* (rounded). A checklist item added to an empty task starts progress tracking; moving progress to 100 % completes the task, and the first progress on a `new` task switches it to `in_progress`.
- **Follow-ups** — post update notes on the task with an **optional progress %**; each update can carry **attachments**. Updates form a chronological thread with author, role, progress badge and timestamp, and notify the creator and the assignee.
- **Priorities** — `low`, `medium`, `high`, `urgent`, shown as coloured badges; task lists can be sorted by priority.
- **Due dates** — start and due dates, shown as **due chips**: red *overdue by n days* when late, amber when due within two days, otherwise *due today*, *due tomorrow*, *due in n days* or the plain date.
- **Assignment and ownership** — assignee, creator and department; a manager can reassign within scope, and only the creator (or admin/executive, or a manager of the assignee) may delete.
- **Filters and sorting** — search by reference/title/description, filter by status, priority, category, assignee and department, "only mine", "only overdue", five sort orders, 20 per page.

---

## Correspondence, meetings, calendar and reports

### Correspondence register

Incoming and outgoing letters in one register (`?p=correspondence`): direction (`incoming` / `outgoing`), subject, party, external reference number, summary, priority, assignee, department, received date and due date. Statuses are `new`, `under_review`, `replied`, `archived`; references use the `IN-…` / `OUT-…` prefixes. Filters cover search, direction, status, priority and due-date sorting, and the letter page allows the assignee (or their manager, an executive or an admin) to change the status and edit the letter.

### Meetings and minutes

Schedule a meeting with agenda, location, start/end and a multi-select of attendees; everyone invited is notified. The meeting page holds:

- an **attendance register** (present / absent per attendee, maintained by the organiser),
- **minutes** with a status change (`scheduled` → `done`, or `cancelled`),
- the attendee list with role and job title.

Only the organiser (or an admin/executive) can change attendance, minutes or status. Past meetings are collapsed under *Past* on the meetings page.

### Calendar

A month grid with **no JavaScript**: tasks by due date, approvals by due date and meetings by start date, colour-coded and linked, with previous/next month and *today*, and Sunday-first weekday labels for the Gulf/MENA week.

### Reports (admin)

A date-range report over the task set with optional status and department filters. It shows summary cards (total, completed, open, overdue, completion %, average completion days, pending approvals, average approval decision days), breakdowns **by status**, **by department**, **by member** and **by approval type**, the latest 100 rows, and a **CSV export** that is itself written to the audit log.

### Audit log (admin)

Every action is recorded — user, action, entity and id, details, IP address and time — and the log is searchable and paginated.

---

## Multi-language — Arabic (default, RTL) and English

| Language | Code | Direction | Notes |
|---|---|---|---|
| العربية | `ar` | **RTL** | Default and **master** language file (`lang/ar.php`) |
| English | `en` | LTR | Full translation (`lang/en.php`) |

- The switcher sits in the top bar (and on the sign-in page and the guide page). Choosing a language reloads the same page with `?lang=xx`.
- The choice is stored in the **session** and in a `daem_lang` **cookie** (one year), so it survives the next visit; the layout sets `dir="rtl"` / `dir="ltr"` accordingly.
- Missing keys fall back to the master language automatically, and the structured guide content is loaded from `lang/doc-<code>.php`.

**Adding another language:**

1. Copy `lang/ar.php` to `lang/xx.php`, translate the **values**, and keep the keys exactly as they are (including `{placeholders}` such as `{n}`, `{title}`).
2. Do the same for the guide: copy `lang/doc-ar.php` to `lang/doc-xx.php` and translate its sections.
3. Add one entry to the `DAEM_LANGS` array in `includes/i18n.php`:

```php
'xx' => ['label' => 'Language name', 'short' => 'XX', 'flag' => '🏳', 'dir' => 'ltr', 'name' => 'Language name'],
```

The new language appears in the switcher immediately; anything not translated falls back to Arabic.

---

## Beginner guide — `?p=doc`

The route `?p=doc` is **public — no login required**. It explains the whole workspace in very simple **A1** language: what Idara is, who uses it (the four roles), how to sign in, the dashboard, tasks, checklists and follow-ups, the approval chain and its three decisions, prioritisation, correspondence, meetings, the calendar, notifications, and the administrator basics — with a small flow diagram and a table of contents.

The page works both ways: signed in it opens inside the normal workspace layout, signed out it renders a standalone shell with the language switcher and a **Sign in** button. Content comes from `lang/doc-ar.php` (master) and its per-language counterparts, so the guide is translated together with the interface.

---

## Quick start (local demo — SQLite, 2 minutes)

Requires PHP 8+ with `pdo_sqlite` (and `mbstring`).

```bash
cd idara-manager
php -S localhost:8000
```

1. Open **http://localhost:8000/install.php**
2. Choose **SQLite**, set the site name and your administrator account → *Install Idara*
3. Open **http://localhost:8000/** and sign in as your administrator
4. Load the demo organisation (8 departments, 10 users, 12 tasks, 8 approvals, 6 letters, 4 meetings, 1 delegation):

```bash
php seed.php
```

The seeder refuses to run if tasks already exist; use `php seed.php --force` to add the demo data anyway.

### Demo accounts (after seeding)

| Role | Username | Password | Name |
|---|---|---|---|
| System Administrator | `admin` | `Admin@1234` | مسؤول النظام |
| Executive Leadership | `abdullah.harbi` | `Manager@1234` | عبدالله الحربي |
| Department Manager | `sarah.qahtani` | `Manager@1234` | سارة القحطاني |
| Department Manager | `mohammed.otaibi` | `Manager@1234` | محمد العتيبي |
| Department Manager | `hind.anazi` | `Manager@1234` | هند العنزي |
| Employee | `noura.shammari` | `User@1234` | نورة الشمري |
| Employee | `fahad.dosari` | `User@1234` | فهد الدوسري |
| Employee | `reem.zahrani` | `User@1234` | ريم الزهراني |
| Employee | `khalid.ghamdi` | `User@1234` | خالد الغامدي |
| Employee | `mona.subaie` | `User@1234` | منى السبيعي |

> The installer also creates your own administrator account. The seeded `admin` / `Admin@1234` account is added by `seed.php`. **Change the demo passwords or skip seeding in production.**

---

## Production deployment (Hostinger, MySQL)

1. In **hPanel → Databases → MySQL Databases**, create a database and a user, and **assign the user to the database with ALL PRIVILEGES**. Note the host (usually `localhost`), database name, user name and password.
2. Upload the ZIP and extract it into the document root (`public_html/…` or your subdomain folder).
3. Open `https://your-domain/install.php`, choose **MySQL**, enter the credentials, the site name, your administrator account and the timezone → *Install Idara*.
4. **Delete `install.php`** from the server.
5. (Optional, via SSH) run the demo seeder once, then delete it:
   ```bash
   php ~/domains/your-domain/public_html/idara-manager/seed.php
   ```
6. Sign in and configure: **Admin → Departments → Users → Categories → Types → Settings**.
7. If the extractor skipped dotfiles, rename **`htaccess.txt` → `.htaccess`** in the project root (the installer also recreates `.htaccess` if it is missing).

The included `.htaccess` works on Hostinger (Apache/LiteSpeed) and blocks `data/`, `storage/`, `includes/`, `sql/` and `lang/`.

> MySQL vs SQLite: the application code is identical — only the schema file and the PDO connection differ. SQLite is for the instant local demo; use MySQL in production.

---

## Project structure

```
idara-manager/
├── index.php              # front controller: route table (?p=…) + role guard
├── install.php            # web installer (SQLite/MySQL) — delete after install
├── seed.php               # optional demo organisation — delete after seeding
├── diag.php               # temporary deployment self-check — delete in production
├── integrity.php          # released-build file manifest (sizes + md5)
├── .htaccess              # hardening + static caching (also shipped as htaccess.txt)
├── includes/
│   ├── bootstrap.php      # config, hardened session, error handling, boot
│   ├── db.php             # PDO wrapper — dual driver (SQLite / MySQL)
│   ├── auth.php           # login, roles, throttling, session TTL
│   ├── helpers.php        # CSRF, dates, uploads, audit, notify, vocabulary
│   ├── models.php         # Departments, Users, Tasks, Approvals, Delegations,
│   │                      # Correspondence, Meetings, Notifications, AuditLog
│   ├── layout.php         # sidebar, topbar, badges, progress bars, tables
│   └── i18n.php           # DAEM_LANGS, t(), language switcher, guide loader
├── pages/                 # one file per route
│   ├── login.php · logout.php · dashboard.php
│   ├── tasks.php · task_view.php · task_new.php
│   ├── approvals.php · approval_view.php · approval_new.php
│   ├── team.php · correspondence.php · letter_view.php · letter_new.php
│   ├── meetings.php · meeting_view.php · calendar.php · delegations.php
│   ├── notifications.php · profile.php · doc.php · api.php · download.php · 404.php
│   └── admin/             # users · departments · categories · types · settings
│                          # audit · reports
├── lang/                  # ar.php (master) · en.php · doc-ar.php · doc-en.php
├── sql/                   # schema.sqlite.sql · schema.mysql.sql
├── assets/                # css/style.css · js/app.js (no dependencies)
├── data/                  # created by the installer: config.php, SQLite file, error.log
└── storage/uploads/       # attachments (randomized stored names)
```

---

## Security built in

- **SQL injection** — every statement is a PDO **prepared statement**; dynamic ordering is whitelisted.
- **Passwords** — `password_hash()` / `password_verify()` (bcrypt by default), minimum 8 characters.
- **CSRF** — a per-session token on **every form**, including the installer and the JSON API, verified with `hash_equals()`.
- **Login throttling** — 5 failed attempts per 15 minutes per username **or** IP, recorded in `login_attempts`.
- **Session hardening** — `use_strict_mode`, cookies only, `HttpOnly`, `SameSite=Lax`, `Secure` under HTTPS, session id regenerated on login, and a **2-hour idle timeout**.
- **Uploads** — extension whitelist (`pdf png jpg jpeg gif txt csv doc docx xls xlsx zip`), **5 MB** cap, randomized stored file names, and downloads served only to users allowed to see the related task/approval (with a MIME whitelist and CRLF-safe filenames).
- **XSS** — all output escaped with `htmlspecialchars(ENT_QUOTES, 'UTF-8')`; pages send `noindex, nofollow`.
- **Web-server hardening** — `.htaccess` disables directory listing, denies `config.php`/`seed.php`/`schema*.sql`, returns 403 for `includes/`, `data/`, `storage/`, `sql/`, `lang/`, and sets `X-Content-Type-Options`, `X-Frame-Options` and `Referrer-Policy`.
- **Audit log** — every action (create, update, decision, login, login failure, export …) is recorded with user, entity, details, IP and time.
- **Error handling** — exceptions are logged to `data/error.log` and shown as a generic page; stack traces are never exposed.

---

## Testing

- **`tests/smoke.sh`** — end-to-end HTTP smoke test: it resets the local database, installs fresh, seeds the demo data, then walks every flow with `curl` (login for each role, task create/update/checklist/follow-up, approval create/decide, correspondence, meetings, reports/CSV, security negatives such as a bad CSRF token and login throttling) and prints a `PASS/FAIL` summary.
- **`tests/i18n-smoke.sh`** — multilingual verification: installs fresh and checks that Arabic and English pages render, that the direction attribute and layout switch correctly, that translated strings are present, and that no untranslated leftovers appear.
- **`diag.php?go=1`** — temporary deployment self-check: PHP version and SAPI, presence of the required extensions, **file integrity against `integrity.php`**, `data/config.php` and the live database connection, table counts, writable `data/` and `storage/` folders, session start, application bootstrap, and the last PHP error. Delete the file after use.
- **`tests/make-integrity.php`** — build tool that regenerates `integrity.php` (size + md5 of every shipped file) after any change; run it from the project root with `php tests/make-integrity.php`.

Run the shell tests against a locally served copy:

```bash
php -S 127.0.0.1:8090 &
bash tests/smoke.sh
bash tests/i18n-smoke.sh
```

---

## Roadmap ideas

Email/SMS notifications for pending approvals and overdue tasks, e-signature on approval decisions, printable official letter and approval templates, Arabic Hijri calendar alongside Gregorian, recurring tasks, approval chain templates per type and department, two-factor sign-in, PDF exports beside CSV, a REST API with API keys for integration with the authority's other systems, and additional languages.

---

© 2026 s0sta — All rights reserved.
