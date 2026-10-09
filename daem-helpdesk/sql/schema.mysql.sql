-- Daem — IT Help Desk · MySQL/MariaDB schema (production, e.g. Hostinger)
--
-- NOTE: foreign keys are intentionally NOT used. Hostinger runs MariaDB,
-- which rejects FK constraints on some configurations (errno 150), and a
-- partially-installed database can also block them. Daem enforces every
-- relationship in the application layer (prepared statements validate all
-- references before writing), so integrity is fully preserved without
-- database-level FK constraints. All performance indexes are kept.

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'user',
  department    VARCHAR(120) NOT NULL DEFAULT '',
  phone         VARCHAR(40)  NOT NULL DEFAULT '',
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL DEFAULT '',
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tickets (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref               VARCHAR(40) NULL UNIQUE,
  subject           VARCHAR(255) NOT NULL,
  description       TEXT NOT NULL,
  category_id       INT UNSIGNED NULL,
  priority          VARCHAR(20) NOT NULL DEFAULT 'medium',
  status            VARCHAR(20) NOT NULL DEFAULT 'new',
  requester_id      INT UNSIGNED NOT NULL,
  assignee_id       INT UNSIGNED NULL,
  first_response_at DATETIME NULL,
  resolved_at       DATETIME NULL,
  closed_at         DATETIME NULL,
  sla_due           DATETIME NULL,
  created_at        DATETIME NOT NULL,
  updated_at        DATETIME NOT NULL,
  INDEX idx_tickets_status    (status),
  INDEX idx_tickets_requester (requester_id),
  INDEX idx_tickets_assignee  (assignee_id),
  INDEX idx_tickets_updated   (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_replies (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id   INT UNSIGNED NOT NULL,
  author_id   INT UNSIGNED NOT NULL,
  body        TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL,
  INDEX idx_replies_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attachments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id     INT UNSIGNED NOT NULL,
  reply_id      INT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(120) NOT NULL,
  mime          VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
  size          INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by   INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL,
  INDEX idx_attach_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kb_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL DEFAULT '',
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kb_articles (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  title       VARCHAR(255) NOT NULL,
  slug        VARCHAR(255) NOT NULL UNIQUE,
  body        TEXT NOT NULL,
  author_id   INT UNSIGNED NULL,
  published   TINYINT(1) NOT NULL DEFAULT 1,
  views       INT UNSIGNED NOT NULL DEFAULT 0,
  helpful     INT UNSIGNED NOT NULL DEFAULT 0,
  not_helpful INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL,
  updated_at  DATETIME NOT NULL,
  INDEX idx_kb_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kb_feedback (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  vote       INT NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_kb_feedback (article_id, user_id),
  INDEX idx_fb_article (article_id),
  INDEX idx_fb_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sla (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  priority         VARCHAR(20) NOT NULL UNIQUE,
  response_hours   DECIMAL(6,2) NOT NULL DEFAULT 24,
  resolution_hours DECIMAL(6,2) NOT NULL DEFAULT 120
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  message    VARCHAR(500) NOT NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_notif_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(190) NOT NULL DEFAULT '',
  ip           VARCHAR(45)  NOT NULL DEFAULT '',
  success      TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL,
  INDEX idx_attempts_lookup (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
