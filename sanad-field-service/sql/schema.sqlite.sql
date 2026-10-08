-- Sanad — Field Service & Job Management · SQLite schema (zero-config demo)
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  username      TEXT NOT NULL UNIQUE,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'technician',
  phone         TEXT NOT NULL DEFAULT '',
  colour        TEXT NOT NULL DEFAULT '',
  active        INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS customers (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  code       TEXT UNIQUE,
  name       TEXT NOT NULL,
  type       TEXT NOT NULL DEFAULT 'company',
  email      TEXT NOT NULL DEFAULT '',
  phone      TEXT NOT NULL DEFAULT '',
  address    TEXT NOT NULL DEFAULT '',
  city       TEXT NOT NULL DEFAULT '',
  tax_no     TEXT NOT NULL DEFAULT '',
  notes      TEXT NOT NULL DEFAULT '',
  active     INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sites (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id   INTEGER NOT NULL,
  name          TEXT NOT NULL,
  address       TEXT NOT NULL DEFAULT '',
  city          TEXT NOT NULL DEFAULT '',
  contact_name  TEXT NOT NULL DEFAULT '',
  contact_phone TEXT NOT NULL DEFAULT '',
  access_notes  TEXT NOT NULL DEFAULT '',
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS services (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  code         TEXT NOT NULL UNIQUE,
  name         TEXT NOT NULL,
  description  TEXT NOT NULL DEFAULT '',
  price        REAL NOT NULL DEFAULT 0,
  duration_min INTEGER NOT NULL DEFAULT 60,
  active       INTEGER NOT NULL DEFAULT 1,
  sort_order   INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS parts (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  sku           TEXT NOT NULL UNIQUE,
  name          TEXT NOT NULL,
  unit          TEXT NOT NULL DEFAULT 'pc',
  stock_qty     REAL NOT NULL DEFAULT 0,
  reorder_level REAL NOT NULL DEFAULT 0,
  cost_price    REAL NOT NULL DEFAULT 0,
  sell_price    REAL NOT NULL DEFAULT 0,
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS contracts (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  number           TEXT UNIQUE,
  customer_id      INTEGER NOT NULL,
  site_id          INTEGER,
  service_id       INTEGER,
  frequency        TEXT NOT NULL DEFAULT 'quarterly',
  price_per_visit  REAL NOT NULL DEFAULT 0,
  start_date       TEXT NOT NULL,
  end_date         TEXT,
  visits_total     INTEGER NOT NULL DEFAULT 0,
  status           TEXT NOT NULL DEFAULT 'active',
  notes            TEXT NOT NULL DEFAULT '',
  created_at       TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS contract_visits (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  contract_id INTEGER NOT NULL,
  due_date    TEXT NOT NULL,
  job_id      INTEGER,
  created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS jobs (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  number         TEXT UNIQUE,
  customer_id    INTEGER NOT NULL,
  site_id        INTEGER,
  service_id     INTEGER,
  contract_id    INTEGER,
  type           TEXT NOT NULL DEFAULT 'one_time',
  priority       TEXT NOT NULL DEFAULT 'normal',
  status         TEXT NOT NULL DEFAULT 'new',
  assigned_to    INTEGER,
  scheduled_date TEXT,
  window_start   TEXT,
  window_end     TEXT,
  title          TEXT NOT NULL,
  description    TEXT NOT NULL DEFAULT '',
  internal_notes TEXT NOT NULL DEFAULT '',
  completed_at   TEXT,
  created_by     INTEGER,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS job_checklist (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id     INTEGER NOT NULL,
  label      TEXT NOT NULL,
  done       INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS job_parts (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id     INTEGER NOT NULL,
  part_id    INTEGER NOT NULL,
  qty        REAL NOT NULL DEFAULT 0,
  unit_price REAL NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS job_notes (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id      INTEGER NOT NULL,
  user_id     INTEGER,
  note        TEXT NOT NULL,
  status_from TEXT,
  status_to   TEXT,
  created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS job_files (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id        INTEGER NOT NULL,
  note_id       INTEGER,
  original_name TEXT NOT NULL,
  stored_name   TEXT NOT NULL,
  mime          TEXT NOT NULL DEFAULT 'application/octet-stream',
  size          INTEGER NOT NULL DEFAULT 0,
  uploaded_by   INTEGER NOT NULL,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS invoices (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  number     TEXT UNIQUE,
  job_id     INTEGER,
  customer_id INTEGER NOT NULL,
  issued_at  TEXT NOT NULL,
  due_at     TEXT,
  subtotal   REAL NOT NULL DEFAULT 0,
  tax_rate   REAL NOT NULL DEFAULT 0,
  tax_amount REAL NOT NULL DEFAULT 0,
  total      REAL NOT NULL DEFAULT 0,
  status     TEXT NOT NULL DEFAULT 'unpaid',
  notes      TEXT NOT NULL DEFAULT '',
  created_by INTEGER,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS invoice_items (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  invoice_id  INTEGER NOT NULL,
  description TEXT NOT NULL,
  qty         REAL NOT NULL DEFAULT 1,
  unit_price  REAL NOT NULL DEFAULT 0,
  amount      REAL NOT NULL DEFAULT 0,
  sort_order  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS payments (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  invoice_id  INTEGER NOT NULL,
  paid_at     TEXT NOT NULL,
  amount      REAL NOT NULL DEFAULT 0,
  method      TEXT NOT NULL DEFAULT 'cash',
  reference   TEXT NOT NULL DEFAULT '',
  recorded_by INTEGER,
  created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS notifications (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL,
  message    TEXT NOT NULL,
  link       TEXT,
  is_read    INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_log (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER,
  username   TEXT NOT NULL DEFAULT 'System',
  action     TEXT NOT NULL,
  entity     TEXT,
  entity_id  TEXT,
  details    TEXT,
  ip         TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
  `key`   TEXT PRIMARY KEY,
  `value` TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  username     TEXT NOT NULL DEFAULT '',
  ip           TEXT NOT NULL DEFAULT '',
  success      INTEGER NOT NULL DEFAULT 0,
  attempted_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_jobs_status    ON jobs(status);
CREATE INDEX IF NOT EXISTS idx_jobs_date      ON jobs(scheduled_date);
CREATE INDEX IF NOT EXISTS idx_jobs_assigned  ON jobs(assigned_to);
CREATE INDEX IF NOT EXISTS idx_jobs_customer  ON jobs(customer_id);
CREATE INDEX IF NOT EXISTS idx_jobs_contract  ON jobs(contract_id);
CREATE INDEX IF NOT EXISTS idx_sites_customer ON sites(customer_id);
CREATE INDEX IF NOT EXISTS idx_visits_contract ON contract_visits(contract_id);
CREATE INDEX IF NOT EXISTS idx_visits_due     ON contract_visits(due_date);
CREATE INDEX IF NOT EXISTS idx_check_job      ON job_checklist(job_id);
CREATE INDEX IF NOT EXISTS idx_jparts_job     ON job_parts(job_id);
CREATE INDEX IF NOT EXISTS idx_jnotes_job     ON job_notes(job_id);
CREATE INDEX IF NOT EXISTS idx_jfiles_job     ON job_files(job_id);
CREATE INDEX IF NOT EXISTS idx_inv_customer   ON invoices(customer_id);
CREATE INDEX IF NOT EXISTS idx_inv_status     ON invoices(status);
CREATE INDEX IF NOT EXISTS idx_inv_job        ON invoices(job_id);
CREATE INDEX IF NOT EXISTS idx_iitems_invoice ON invoice_items(invoice_id);
CREATE INDEX IF NOT EXISTS idx_pay_invoice    ON payments(invoice_id);
CREATE INDEX IF NOT EXISTS idx_notif_user     ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_created  ON audit_log(created_at);
