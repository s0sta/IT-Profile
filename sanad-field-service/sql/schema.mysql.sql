-- Sanad — Field Service & Job Management · MySQL/MariaDB schema (production)
--
-- NOTE: foreign keys are intentionally NOT used (Hostinger runs MariaDB, which
-- rejects FK constraints on some configurations — errno 150). Sanad enforces
-- every relationship in the application layer. All indexes are kept.

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'technician',
  phone         VARCHAR(40)  NOT NULL DEFAULT '',
  colour        VARCHAR(9)   NOT NULL DEFAULT '',
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(30) NULL UNIQUE,
  name       VARCHAR(180) NOT NULL,
  type       VARCHAR(20) NOT NULL DEFAULT 'company',
  email      VARCHAR(190) NOT NULL DEFAULT '',
  phone      VARCHAR(40) NOT NULL DEFAULT '',
  address    VARCHAR(255) NOT NULL DEFAULT '',
  city       VARCHAR(120) NOT NULL DEFAULT '',
  tax_no     VARCHAR(60) NOT NULL DEFAULT '',
  notes      TEXT NULL,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sites (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id   INT UNSIGNED NOT NULL,
  name          VARCHAR(180) NOT NULL,
  address       VARCHAR(255) NOT NULL DEFAULT '',
  city          VARCHAR(120) NOT NULL DEFAULT '',
  contact_name  VARCHAR(150) NOT NULL DEFAULT '',
  contact_phone VARCHAR(40) NOT NULL DEFAULT '',
  access_notes  VARCHAR(500) NOT NULL DEFAULT '',
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL,
  INDEX idx_sites_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(40) NOT NULL UNIQUE,
  name         VARCHAR(180) NOT NULL,
  description  VARCHAR(500) NOT NULL DEFAULT '',
  price        DECIMAL(12,2) NOT NULL DEFAULT 0,
  duration_min INT NOT NULL DEFAULT 60,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  sort_order   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku           VARCHAR(60) NOT NULL UNIQUE,
  name          VARCHAR(180) NOT NULL,
  unit          VARCHAR(20) NOT NULL DEFAULT 'pc',
  stock_qty     DECIMAL(12,2) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
  sell_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number          VARCHAR(40) NULL UNIQUE,
  customer_id     INT UNSIGNED NOT NULL,
  site_id         INT UNSIGNED NULL,
  service_id      INT UNSIGNED NULL,
  frequency       VARCHAR(20) NOT NULL DEFAULT 'quarterly',
  price_per_visit DECIMAL(12,2) NOT NULL DEFAULT 0,
  start_date      DATE NOT NULL,
  end_date        DATE NULL,
  visits_total    INT NOT NULL DEFAULT 0,
  status          VARCHAR(20) NOT NULL DEFAULT 'active',
  notes           TEXT NULL,
  created_at      DATETIME NOT NULL,
  INDEX idx_contracts_status (status),
  INDEX idx_contracts_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_visits (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  due_date    DATE NOT NULL,
  job_id      INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL,
  INDEX idx_visits_contract (contract_id),
  INDEX idx_visits_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number         VARCHAR(40) NULL UNIQUE,
  customer_id    INT UNSIGNED NOT NULL,
  site_id        INT UNSIGNED NULL,
  service_id     INT UNSIGNED NULL,
  contract_id    INT UNSIGNED NULL,
  type           VARCHAR(20) NOT NULL DEFAULT 'one_time',
  priority       VARCHAR(20) NOT NULL DEFAULT 'normal',
  status         VARCHAR(20) NOT NULL DEFAULT 'new',
  assigned_to    INT UNSIGNED NULL,
  scheduled_date DATE NULL,
  window_start   VARCHAR(10) NULL,
  window_end     VARCHAR(10) NULL,
  title          VARCHAR(200) NOT NULL,
  description    TEXT NULL,
  internal_notes TEXT NULL,
  completed_at   DATETIME NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL,
  updated_at     DATETIME NOT NULL,
  INDEX idx_jobs_status   (status),
  INDEX idx_jobs_date     (scheduled_date),
  INDEX idx_jobs_assigned (assigned_to),
  INDEX idx_jobs_customer (customer_id),
  INDEX idx_jobs_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_checklist (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id     INT UNSIGNED NOT NULL,
  label      VARCHAR(255) NOT NULL,
  done       TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  INDEX idx_check_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_parts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id     INT UNSIGNED NOT NULL,
  part_id    INT UNSIGNED NOT NULL,
  qty        DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_jparts_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_notes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id      INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  note        TEXT NOT NULL,
  status_from VARCHAR(20) NULL,
  status_to   VARCHAR(20) NULL,
  created_at  DATETIME NOT NULL,
  INDEX idx_jnotes_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id        INT UNSIGNED NOT NULL,
  note_id       INT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(120) NOT NULL,
  mime          VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
  size          INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by   INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL,
  INDEX idx_jfiles_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number      VARCHAR(40) NULL UNIQUE,
  job_id      INT UNSIGNED NULL,
  customer_id INT UNSIGNED NOT NULL,
  issued_at   DATE NOT NULL,
  due_at      DATE NULL,
  subtotal    DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_rate    DECIMAL(5,2) NOT NULL DEFAULT 0,
  tax_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
  total       DECIMAL(12,2) NOT NULL DEFAULT 0,
  status      VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  notes       VARCHAR(500) NOT NULL DEFAULT '',
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL,
  INDEX idx_inv_customer (customer_id),
  INDEX idx_inv_status   (status),
  INDEX idx_inv_job      (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  qty         DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  sort_order  INT NOT NULL DEFAULT 0,
  INDEX idx_iitems_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT UNSIGNED NOT NULL,
  paid_at     DATE NOT NULL,
  amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  method      VARCHAR(20) NOT NULL DEFAULT 'cash',
  reference   VARCHAR(120) NOT NULL DEFAULT '',
  recorded_by INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL,
  INDEX idx_pay_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  message    VARCHAR(500) NOT NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_notif_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  username   VARCHAR(120) NOT NULL DEFAULT 'System',
  action     VARCHAR(60) NOT NULL,
  entity     VARCHAR(60) NULL,
  entity_id  VARCHAR(40) NULL,
  details    VARCHAR(500) NULL,
  ip         VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(190) NOT NULL DEFAULT '',
  ip           VARCHAR(45)  NOT NULL DEFAULT '',
  success      TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL,
  INDEX idx_attempts_lookup (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
