-- Daem — IT Help Desk · SQLite schema (zero-config demo mode)
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  username      TEXT NOT NULL UNIQUE,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'user',
  department    TEXT NOT NULL DEFAULT '',
  phone         TEXT NOT NULL DEFAULT '',
  active        INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS categories (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT NOT NULL UNIQUE,
  description TEXT NOT NULL DEFAULT '',
  active      INTEGER NOT NULL DEFAULT 1,
  sort_order  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS tickets (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  ref                TEXT UNIQUE,
  subject            TEXT NOT NULL,
  description        TEXT NOT NULL,
  category_id        INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  priority           TEXT NOT NULL DEFAULT 'medium',
  status             TEXT NOT NULL DEFAULT 'new',
  requester_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  assignee_id        INTEGER REFERENCES users(id) ON DELETE SET NULL,
  first_response_at  TEXT,
  resolved_at        TEXT,
  closed_at          TEXT,
  sla_due            TEXT,
  created_at         TEXT NOT NULL,
  updated_at         TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS ticket_replies (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id   INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
  author_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body        TEXT NOT NULL,
  is_internal INTEGER NOT NULL DEFAULT 0,
  created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS attachments (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id     INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
  reply_id      INTEGER REFERENCES ticket_replies(id) ON DELETE SET NULL,
  original_name TEXT NOT NULL,
  stored_name   TEXT NOT NULL,
  mime          TEXT NOT NULL DEFAULT 'application/octet-stream',
  size          INTEGER NOT NULL DEFAULT 0,
  uploaded_by   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS kb_categories (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT NOT NULL UNIQUE,
  description TEXT NOT NULL DEFAULT '',
  sort_order  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS kb_articles (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  category_id    INTEGER REFERENCES kb_categories(id) ON DELETE SET NULL,
  title          TEXT NOT NULL,
  slug           TEXT NOT NULL UNIQUE,
  body           TEXT NOT NULL,
  author_id      INTEGER REFERENCES users(id) ON DELETE SET NULL,
  published      INTEGER NOT NULL DEFAULT 1,
  views          INTEGER NOT NULL DEFAULT 0,
  helpful        INTEGER NOT NULL DEFAULT 0,
  not_helpful    INTEGER NOT NULL DEFAULT 0,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS kb_feedback (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  article_id INTEGER NOT NULL REFERENCES kb_articles(id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  vote       INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE (article_id, user_id)
);

CREATE TABLE IF NOT EXISTS sla (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  priority         TEXT NOT NULL UNIQUE,
  response_hours   REAL NOT NULL DEFAULT 24,
  resolution_hours REAL NOT NULL DEFAULT 120
);

CREATE TABLE IF NOT EXISTS settings (
  `key`   TEXT PRIMARY KEY,
  `value` TEXT NOT NULL DEFAULT ''
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

CREATE TABLE IF NOT EXISTS notifications (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  message    TEXT NOT NULL,
  link       TEXT,
  is_read    INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  username     TEXT NOT NULL DEFAULT '',
  ip           TEXT NOT NULL DEFAULT '',
  success      INTEGER NOT NULL DEFAULT 0,
  attempted_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_tickets_status   ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_requester ON tickets(requester_id);
CREATE INDEX IF NOT EXISTS idx_tickets_assignee ON tickets(assignee_id);
CREATE INDEX IF NOT EXISTS idx_tickets_updated  ON tickets(updated_at);
CREATE INDEX IF NOT EXISTS idx_replies_ticket   ON ticket_replies(ticket_id);
CREATE INDEX IF NOT EXISTS idx_attach_ticket    ON attachments(ticket_id);
CREATE INDEX IF NOT EXISTS idx_notif_user       ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_created    ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_attempts_lookup  ON login_attempts(attempted_at);
