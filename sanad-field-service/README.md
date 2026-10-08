# ◆ Sanad — Field Service & Job Management Platform

**The daily work organiser for a service company.** Sanad plans the jobs, sends the technicians, records what was done (parts, photos, notes), watches the maintenance contracts, writes the invoices and shows where the money is. Built for A/C, plumbing, electrical, cleaning, pest control — any business that sends people to customers.

Zero frameworks, zero composer, no build step: plain **PHP 8 + PDO** (SQLite *or* MySQL) with vanilla JS/CSS. Upload it and it runs. Available in **English, German and Arabic (RTL)**.

---

## What it does

### Jobs (work orders)
- Every job: number (`JOB-2026-00042`), customer, service address, service, priority, status, technician, day and time window
- **Status workflow**: new → scheduled → in progress → on hold → done / cancelled — every change is written into the job log
- **Checklist** per job with tick-off steps
- **Parts used** per job, taken out of stock automatically
- **Work log**: notes from the technician, the office and the status changes, plus **photo/document attachments** (5 MB each, access-checked download)

### Dispatch board
- One column per technician plus an **“Not assigned”** column
- Move a job to a technician with one click, change the status inside the card
- Day navigation (previous / next / pick a date) — the whole day in one screen

### Customers & service addresses
- Customers (company or private person) with code, tax number and contact details
- **Several service addresses per customer**, each with its own contact person and **access notes** (“gate code 4455, dog in the garden”)
- Customer page: jobs, contracts, invoices and open balance in one place

### Maintenance contracts (AMC)
- Frequency: monthly · every 3 months · every 6 months · yearly, with price per visit
- Sanad **plans all visits automatically** from start to end date
- “Visits waiting for a job” queue: one click turns a due visit into a real job — with the right customer, address and service
- Contracts ending soon, monthly recurring value

### Parts stock
- Part number, unit, cost price, selling price, stock and **reorder level**
- Stock goes down automatically when a part is used on a job; “Add to stock” for goods received
- **Low-stock list** and an automatic warning to the office

### Invoices, VAT & payments
- **One click from a finished job**: the service price and every part used become invoice lines
- Add or remove lines, set the VAT rate, due date from the payment terms
- **Record payments** (cash, card, transfer, cheque) — the status becomes *partly paid* or *paid* by itself
- **Printable invoice sheet** (clean A4 layout, works in all three languages)
- Invoice list with totals, open amounts and an **aging report** (not due · 1–30 · 31–60 · 60+ days)

### Dashboard & reports
- Dashboard adapts to the role: the dispatcher sees today’s work, unassigned jobs, late jobs, due visits, low stock and technician workload; the technician sees only their own jobs; the accountant sees billed, received, outstanding and aging
- Reports: jobs in a period, finished, open, late, **average days to finish**, billed vs received, breakdowns by status, technician, service, customer and parts used — with **CSV export**

### Governance & security
- Roles: **Administrator** · **Dispatcher** (plans the work) · **Technician** (sees *only their own* jobs) · **Accountant** (invoices, payments, reports)
- Audit log of every change (user, action, object, details, IP) and daily notifications (due visits, late jobs, low stock)
- PDO prepared statements, `password_hash()`, CSRF token on every form, login throttling (5 tries / 15 min), hardened sessions, upload whitelist, `.htaccess` blocking `includes/ data/ storage/ sql/ lang/`

---

## Quick start (local demo — SQLite, 2 minutes)

Requires PHP ≥ 8.1 with `pdo_sqlite`.

```bash
cd sanad-field-service
php -S localhost:8000
```

1. Open **http://localhost:8000/install.php** → choose **SQLite**, set the business name and your admin account
2. Open **http://localhost:8000/** and sign in
3. Load demo data (8 customers, 10 addresses, 5 contracts, 18 jobs, 7 invoices):
   ```bash
   php seed.php
   ```

### Demo accounts (after seeding)

| Role | Username | Password |
|---|---|---|
| Administrator | *your admin* (from the installer) | *your password* |
| Dispatcher | `noura.dispatch` | `Demo@1234` |
| Technician | `ahmed.tech` · `bilal.tech` · `sameer.tech` | `Demo@1234` |
| Accountant | `rana.accounts` | `Demo@1234` |

Try this 2-minute tour: sign in as the **dispatcher** → *Dispatch board* → give the urgent job to a technician → sign in as that **technician** → open the job, set *In progress*, tick the checklist, add a part and a photo → set *Done* → sign in as the **accountant** → open the job, *Create invoice*, record a payment, print it.

---

## Production deployment (Hostinger)

1. hPanel → **Subdomains** → create e.g. `sanad` (document root `public_html/sanad`)
2. hPanel → **Databases → MySQL Databases** → create a database + user, then **assign the user to the database with ALL PRIVILEGES**
3. File Manager → upload the ZIP into the subdomain folder → **Extract** (result: `public_html/sanad/index.php`)
4. Open `https://sanad.your-domain.com/install.php` → **MySQL** → host `localhost`, port `3306`, database name, user (both with your account prefix) and password
5. **Delete `install.php`** afterwards; if `.htaccess` is missing, rename `htaccess.txt` → `.htaccess`

The `diag.php` self-check (open `diag.php?go=1`) verifies file integrity, the database, sessions and folder permissions — delete it in production.

---

## Languages — English · Deutsch · العربية

- UI strings: `lang/en.php`, `lang/de.php`, `lang/ar.php` (**identical key sets**, verified by the test suite), documentation in `lang/doc-<lang>.php`
- Switch language from the top bar or the sign-in page; stored in session + cookie
- **Arabic runs right-to-left** — the whole layout, the dispatch board and the invoice sheet mirror
- Public beginner guide at **`?p=doc`** — 15 illustrated sections at A1 reading level: what Sanad is, the four roles, planning the day, creating jobs, the technician workflow, parts, contracts, invoices, reports, alerts, admin setup — plus a job-lifecycle diagram (New → Scheduled → In progress → Done → Invoiced → Paid)
- Adding a language: copy `lang/en.php`, translate the values (keep the keys and `{placeholders}`), do the same for `lang/doc-en.php`, then add one line to `SANAD_LANGS` in `includes/i18n.php`

## Tests

- `tests/smoke.sh` — **89 end-to-end checks**: install, seed, all pages, job create/edit, the technician workflow (status, checklist, parts + stock movement, notes), invoice from job (lines, VAT, payments, status transitions, print view), customers + service addresses, contracts + visit planning + job from visit, parts stock, reports + CSV export, **role enforcement for all four roles**, CSRF rejection, throttling, session requirement
- `tests/i18n-smoke.sh` — multilingual checks: key parity, 22 pages × 3 languages, RTL, translated content, no English leftovers
- `tests/make-integrity.php` — regenerates `integrity.php` after any file change

## Project structure

```
sanad-field-service/
├── index.php            # front controller (routes + role enforcement)
├── install.php          # web installer (SQLite/MySQL) — delete after install
├── seed.php             # demo data — delete after seeding
├── diag.php             # deployment self-check — delete in production
├── .htaccess / htaccess.txt
├── sql/                 # schema.sqlite.sql · schema.mysql.sql (18 tables)
├── includes/            # bootstrap · db · auth · helpers · models · layout · i18n · job_form · customer_form
├── lang/                # en.php · de.php · ar.php · doc-en.php · doc-de.php · doc-ar.php
├── pages/               # dashboard, board, jobs, job, job_new, job_edit, customers, customer,
│                        # customer_new/edit, contracts, contract, parts, invoices, invoice,
│                        # reports, notifications, profile, login, doc, api, download + admin/*
├── assets/              # css/style.css · js/app.js (no dependencies)
└── tests/               # smoke.sh · i18n-smoke.sh · make-integrity.php
```

## Roadmap ideas

Customer portal (job status link), technician mobile view with GPS check-in, WhatsApp/SMS notification on the way, recurring invoices for contracts, purchase orders to suppliers, barcode scanning of parts, quotation → job → invoice flow, and an integration with **Jard** (the IT asset register) for the tools and the **Daem** help desk for internal tickets.

---

© 2026 s0sta — All rights reserved.
