-- Idara — Manager Workspace · MySQL/MariaDB schema (production, e.g. Hostinger)
--
-- NOTE: foreign keys are intentionally NOT used (Hostinger runs MariaDB, which
-- rejects FK formation on some configurations). Every relationship is enforced
-- in the application layer. All performance indexes are kept.

CREATE TABLE IF NOT EXISTS departments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_ar    VARCHAR(160) NOT NULL,
  name_en    VARCHAR(160) NOT NULL DEFAULT '',
  code       VARCHAR(30)  NOT NULL DEFAULT '',
  manager_id INT UNSIGNED NULL,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(160) NOT NULL,
  name_en       VARCHAR(160) NOT NULL DEFAULT '',
  username      VARCHAR(60)  NOT NULL UNIQUE,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'member',
  department_id INT UNSIGNED NULL,
  job_title     VARCHAR(160) NOT NULL DEFAULT '',
  phone         VARCHAR(40)  NOT NULL DEFAULT '',
  manager_id    INT UNSIGNED NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL,
  INDEX idx_users_dept (department_id),
  INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_ar    VARCHAR(160) NOT NULL,
  name_en    VARCHAR(160) NOT NULL DEFAULT '',
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref           VARCHAR(40) NULL UNIQUE,
  title         VARCHAR(255) NOT NULL,
  description   TEXT NOT NULL,
  category_id   INT UNSIGNED NULL,
  priority      VARCHAR(20) NOT NULL DEFAULT 'medium',
  status        VARCHAR(20) NOT NULL DEFAULT 'new',
  progress      INT NOT NULL DEFAULT 0,
  creator_id    INT UNSIGNED NOT NULL,
  assignee_id   INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  start_date    DATE NULL,
  due_date      DATE NULL,
  completed_at  DATETIME NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  INDEX idx_tasks_assignee (assignee_id),
  INDEX idx_tasks_status   (status),
  INDEX idx_tasks_due      (due_date),
  INDEX idx_tasks_dept     (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(255) NOT NULL,
  is_done    TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_titems_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_updates (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id    INT UNSIGNED NOT NULL,
  author_id  INT UNSIGNED NOT NULL,
  body       TEXT NOT NULL,
  progress   INT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_tupdates_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attachments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id       INT UNSIGNED NULL,
  update_id     INT UNSIGNED NULL,
  approval_id   INT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(120) NOT NULL,
  mime          VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
  size          INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by   INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL,
  INDEX idx_attach_task (task_id),
  INDEX idx_attach_approval (approval_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS approval_types (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_ar    VARCHAR(160) NOT NULL,
  name_en    VARCHAR(160) NOT NULL DEFAULT '',
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS approvals (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref             VARCHAR(40) NULL UNIQUE,
  title           VARCHAR(255) NOT NULL,
  description     TEXT NOT NULL,
  type_id         INT UNSIGNED NULL,
  requester_id    INT UNSIGNED NOT NULL,
  department_id   INT UNSIGNED NULL,
  priority        VARCHAR(20) NOT NULL DEFAULT 'medium',
  status          VARCHAR(20) NOT NULL DEFAULT 'pending',
  current_step    INT NOT NULL DEFAULT 1,
  due_date        DATE NULL,
  related_task_id INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL,
  updated_at      DATETIME NOT NULL,
  closed_at       DATETIME NULL,
  INDEX idx_approvals_status (status),
  INDEX idx_approvals_dept   (department_id),
  INDEX idx_approvals_req    (requester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS approval_steps (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  approval_id INT UNSIGNED NOT NULL,
  step_order  INT NOT NULL DEFAULT 1,
  approver_id INT UNSIGNED NOT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'pending',
  note        TEXT NOT NULL,
  decided_at  DATETIME NULL,
  created_at  DATETIME NOT NULL,
  INDEX idx_steps_approver (approver_id),
  INDEX idx_steps_approval (approval_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS delegations (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delegator_id INT UNSIGNED NOT NULL,
  delegate_id  INT UNSIGNED NOT NULL,
  starts_at    DATE NOT NULL,
  ends_at      DATE NOT NULL,
  reason       VARCHAR(255) NOT NULL DEFAULT '',
  active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL,
  INDEX idx_deleg_delegate (delegate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS correspondence (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref           VARCHAR(40) NULL UNIQUE,
  direction     VARCHAR(20) NOT NULL DEFAULT 'incoming',
  subject       VARCHAR(255) NOT NULL,
  summary       TEXT NOT NULL,
  party         VARCHAR(190) NOT NULL DEFAULT '',
  reference_no  VARCHAR(80)  NOT NULL DEFAULT '',
  priority      VARCHAR(20) NOT NULL DEFAULT 'medium',
  status        VARCHAR(20) NOT NULL DEFAULT 'new',
  assignee_id   INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  received_at   DATE NULL,
  due_date      DATE NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  INDEX idx_corr_status   (status),
  INDEX idx_corr_assignee (assignee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meetings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  agenda       TEXT NOT NULL,
  location     VARCHAR(160) NOT NULL DEFAULT '',
  starts_at    DATETIME NOT NULL,
  ends_at      DATETIME NULL,
  organizer_id INT UNSIGNED NOT NULL,
  minutes      TEXT NOT NULL,
  status       VARCHAR(20) NOT NULL DEFAULT 'scheduled',
  created_at   DATETIME NOT NULL,
  updated_at   DATETIME NOT NULL,
  INDEX idx_meet_starts (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meeting_attendees (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  attended   TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_meet_att_meeting (meeting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  username   VARCHAR(160) NOT NULL DEFAULT 'System',
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
