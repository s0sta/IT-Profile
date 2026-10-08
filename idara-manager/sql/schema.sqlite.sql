-- Idara — Manager Workspace · SQLite schema (zero-config demo mode)
-- No FOREIGN KEY constraints (portable across SQLite/MariaDB). Integrity is enforced in the app.

CREATE TABLE IF NOT EXISTS departments (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name_ar     TEXT NOT NULL,
  name_en     TEXT NOT NULL DEFAULT '',
  code        TEXT NOT NULL DEFAULT '',
  manager_id  INTEGER,
  active      INTEGER NOT NULL DEFAULT 1,
  sort_order  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  name_en       TEXT NOT NULL DEFAULT '',
  username      TEXT NOT NULL UNIQUE,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'member',
  department_id INTEGER,
  job_title     TEXT NOT NULL DEFAULT '',
  phone         TEXT NOT NULL DEFAULT '',
  manager_id    INTEGER,
  active        INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS task_categories (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name_ar    TEXT NOT NULL,
  name_en    TEXT NOT NULL DEFAULT '',
  active     INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS tasks (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  ref           TEXT UNIQUE,
  title         TEXT NOT NULL,
  description   TEXT NOT NULL DEFAULT '',
  category_id   INTEGER,
  priority      TEXT NOT NULL DEFAULT 'medium',
  status        TEXT NOT NULL DEFAULT 'new',
  progress      INTEGER NOT NULL DEFAULT 0,
  creator_id    INTEGER NOT NULL,
  assignee_id   INTEGER,
  department_id INTEGER,
  start_date    TEXT,
  due_date      TEXT,
  completed_at  TEXT,
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS task_items (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id    INTEGER NOT NULL,
  title      TEXT NOT NULL,
  is_done    INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS task_updates (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id    INTEGER NOT NULL,
  author_id  INTEGER NOT NULL,
  body       TEXT NOT NULL,
  progress   INTEGER,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS attachments (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id       INTEGER,
  update_id     INTEGER,
  approval_id   INTEGER,
  original_name TEXT NOT NULL,
  stored_name   TEXT NOT NULL,
  mime          TEXT NOT NULL DEFAULT 'application/octet-stream',
  size          INTEGER NOT NULL DEFAULT 0,
  uploaded_by   INTEGER NOT NULL,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS approval_types (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name_ar    TEXT NOT NULL,
  name_en    TEXT NOT NULL DEFAULT '',
  active     INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS approvals (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  ref             TEXT UNIQUE,
  title           TEXT NOT NULL,
  description     TEXT NOT NULL DEFAULT '',
  type_id         INTEGER,
  requester_id    INTEGER NOT NULL,
  department_id   INTEGER,
  priority        TEXT NOT NULL DEFAULT 'medium',
  status          TEXT NOT NULL DEFAULT 'pending',
  current_step    INTEGER NOT NULL DEFAULT 1,
  due_date        TEXT,
  related_task_id INTEGER,
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL,
  closed_at       TEXT
);

CREATE TABLE IF NOT EXISTS approval_steps (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  approval_id INTEGER NOT NULL,
  step_order  INTEGER NOT NULL DEFAULT 1,
  approver_id INTEGER NOT NULL,
  status      TEXT NOT NULL DEFAULT 'pending',
  note        TEXT NOT NULL DEFAULT '',
  decided_at  TEXT,
  created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS delegations (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  delegator_id INTEGER NOT NULL,
  delegate_id  INTEGER NOT NULL,
  starts_at    TEXT NOT NULL,
  ends_at      TEXT NOT NULL,
  reason       TEXT NOT NULL DEFAULT '',
  active       INTEGER NOT NULL DEFAULT 1,
  created_at   TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS correspondence (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  ref           TEXT UNIQUE,
  direction     TEXT NOT NULL DEFAULT 'incoming',
  subject       TEXT NOT NULL,
  summary       TEXT NOT NULL DEFAULT '',
  party         TEXT NOT NULL DEFAULT '',
  reference_no  TEXT NOT NULL DEFAULT '',
  priority      TEXT NOT NULL DEFAULT 'medium',
  status        TEXT NOT NULL DEFAULT 'new',
  assignee_id   INTEGER,
  department_id INTEGER,
  received_at   TEXT,
  due_date      TEXT,
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS meetings (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  title        TEXT NOT NULL,
  agenda       TEXT NOT NULL DEFAULT '',
  location     TEXT NOT NULL DEFAULT '',
  starts_at    TEXT NOT NULL,
  ends_at      TEXT,
  organizer_id INTEGER NOT NULL,
  minutes      TEXT NOT NULL DEFAULT '',
  status       TEXT NOT NULL DEFAULT 'scheduled',
  created_at   TEXT NOT NULL,
  updated_at   TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS meeting_attendees (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  meeting_id INTEGER NOT NULL,
  user_id    INTEGER NOT NULL,
  attended   INTEGER NOT NULL DEFAULT 0
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
  user_id    INTEGER NOT NULL,
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

CREATE INDEX IF NOT EXISTS idx_tasks_assignee   ON tasks(assignee_id);
CREATE INDEX IF NOT EXISTS idx_tasks_status     ON tasks(status);
CREATE INDEX IF NOT EXISTS idx_tasks_due        ON tasks(due_date);
CREATE INDEX IF NOT EXISTS idx_tasks_dept       ON tasks(department_id);
CREATE INDEX IF NOT EXISTS idx_titems_task      ON task_items(task_id);
CREATE INDEX IF NOT EXISTS idx_tupdates_task    ON task_updates(task_id);
CREATE INDEX IF NOT EXISTS idx_approvals_status ON approvals(status);
CREATE INDEX IF NOT EXISTS idx_approvals_dept   ON approvals(department_id);
CREATE INDEX IF NOT EXISTS idx_steps_approver   ON approval_steps(approver_id);
CREATE INDEX IF NOT EXISTS idx_steps_approval   ON approval_steps(approval_id);
CREATE INDEX IF NOT EXISTS idx_corr_status      ON correspondence(status);
CREATE INDEX IF NOT EXISTS idx_corr_assignee    ON correspondence(assignee_id);
CREATE INDEX IF NOT EXISTS idx_meet_starts      ON meetings(starts_at);
CREATE INDEX IF NOT EXISTS idx_meet_att_meeting ON meeting_attendees(meeting_id);
CREATE INDEX IF NOT EXISTS idx_notif_user       ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_created    ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_attempts_lookup  ON login_attempts(attempted_at);
