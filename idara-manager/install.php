<?php
declare(strict_types=1);

/**
 * Idara — Manager Workspace · Installer
 * Creates data/config.php, builds the schema (SQLite or MySQL), writes
 * default settings + SLA policies, and creates the first administrator.
 *
 * After a successful install, DELETE THIS FILE from the server.
 */

define('APP_ROOT', __DIR__);
define('CONFIG_FILE', APP_ROOT . '/data/config.php');

require_once APP_ROOT . '/includes/db.php';

/** Install-time helper: returns the first column of the first row. */
function install_value(string $sql, array $params = [])
{
    $st = Database::pdo()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

/**
 * Web-server hardening rules. The same rules ship as htaccess.txt because many
 * ZIP extractors silently skip dotfiles — the installer recreates .htaccess.
 */
$htaccessRules = <<<'RULES'
# Idara — Manager Workspace
# Basic hardening (works on Apache / LiteSpeed — Hostinger)

Options -Indexes

<FilesMatch "^(config\.php|seed\.php|schema.*\.sql)$">
  Require all denied
</FilesMatch>

RedirectMatch 403 ^/(includes|data|storage|sql|lang)/

# Security headers
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "same-origin"
</IfModule>

# Basic caching for static assets
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 hour"
  ExpiresByType application/javascript "access plus 1 hour"
</IfModule>
RULES;

session_name('DAEMINSTALL');
session_start();

$existingConfig = null;
$configBroken = false;
$configError = '';

if (is_file(CONFIG_FILE)) {
    $existingConfig = require CONFIG_FILE;

    // Does the stored configuration still connect? If not, allow re-configuring
    // instead of dead-ending on "already installed" (this happens when a data/
    // folder from another machine is restored/exported by mistake).
    try {
        if (is_array($existingConfig) && isset($existingConfig['db']) && is_array($existingConfig['db'])) {
            Database::init($existingConfig['db']);
            Database::value('SELECT 1');
        } else {
            throw new RuntimeException('the config file does not contain a valid Idara configuration');
        }
    } catch (Throwable $e) {
        $configBroken = true;
        $configError = $e->getMessage();
    }

    if (!$configBroken && !isset($_GET['reconfigure']) && !isset($_GET['done'])) {
        die('Idara is already installed and its database connection works. '
            . '<a href="index.php">Open Idara</a> · '
            . '<a href="install.php?reconfigure=1">Reconfigure</a>');
    }
}

$errors = [];
$old    = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---- CSRF (same pattern as the app, standalone here) ----
    if (empty($_SESSION['icsrf'])) {
        $_SESSION['icsrf'] = bin2hex(random_bytes(32));
    }
    $tokenOk = isset($_POST['csrf']) && hash_equals($_SESSION['icsrf'], (string) $_POST['csrf']);
    if (!$tokenOk) {
        $errors[] = 'Invalid security token. Please submit the form again.';
    }

    $driver   = ($_POST['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    $siteName = trim((string) ($_POST['site_name'] ?? ''));
    $admin    = [
        'name'     => trim((string) ($_POST['admin_name'] ?? '')),
        'username' => trim((string) ($_POST['admin_username'] ?? '')),
        'email'    => trim((string) ($_POST['admin_email'] ?? '')),
        'password' => (string) ($_POST['admin_password'] ?? ''),
    ];
    $tz = (string) ($_POST['timezone'] ?? 'Asia/Riyadh');

    if ($siteName === '' || mb_strlen($siteName) > 60) {
        $errors[] = 'Enter a site name (max 60 characters).';
    }
    if ($admin['name'] === '') {
        $errors[] = 'Enter the administrator full name.';
    }
    if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $admin['username'])) {
        $errors[] = 'Username must be 3–40 characters (letters, numbers, dot, dash, underscore).';
    }
    if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid administrator email.';
    }
    if (strlen($admin['password']) < 8) {
        $errors[] = 'Administrator password must be at least 8 characters.';
    }
    if (!in_array($tz, timezone_identifiers_list(), true)) {
        $errors[] = 'Invalid timezone.';
    }

    $db = null;
    if (!$errors) {
        if ($driver === 'sqlite') {
            $db = [
                'driver' => 'sqlite',
                'path'   => APP_ROOT . '/data/idara.sqlite',
            ];
        } else {
            $db = [
                'driver' => 'mysql',
                'host'   => trim((string) ($_POST['db_host'] ?? '')),
                'port'   => trim((string) ($_POST['db_port'] ?? '3306')),
                'name'   => trim((string) ($_POST['db_name'] ?? '')),
                'user'   => trim((string) ($_POST['db_user'] ?? '')),
                'pass'   => (string) ($_POST['db_pass'] ?? ''),
            ];
            if ($db['host'] === '' || $db['name'] === '' || $db['user'] === '') {
                $errors[] = 'MySQL host, database name and user are required.';
            }
            if (!ctype_digit($db['port']) || (int) $db['port'] < 1 || (int) $db['port'] > 65535) {
                $errors[] = 'MySQL port must be a number between 1 and 65535.';
            }
        }
    }

    if (!$errors) {
        try {
            Database::init($db);
            $schemaFile = $driver === 'sqlite'
                ? APP_ROOT . '/sql/schema.sqlite.sql'
                : APP_ROOT . '/sql/schema.mysql.sql';
            $sql = (string) file_get_contents($schemaFile);

            // Remove full-line comments BEFORE splitting on ';' — a semicolon
            // inside a comment must not split a statement, and a comment block
            // above a statement must never hide it.
            $kept = [];
            foreach (preg_split('/\r\n|\r|\n/', $sql) as $line) {
                if (preg_match('/^\s*--/', $line)) {
                    continue;
                }
                $kept[] = $line;
            }
            foreach (explode(';', implode("\n", $kept)) as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }
                Database::pdo()->exec($statement);
            }

            // Defaults — idempotent, so re-running after a failed attempt always works.
            foreach ([
                'site_name'         => $siteName,
                'task_prefix'       => 'TSK',
                'approval_prefix'   => 'APR',
                'corr_in_prefix'    => 'IN',
                'corr_out_prefix'   => 'OUT',
            ] as $k => $v) {
                if (install_value('SELECT COUNT(*) FROM settings WHERE `key` = ?', [$k])) {
                    Database::exec('UPDATE settings SET `value` = ? WHERE `key` = ?', [$v, $k]);
                } else {
                    Database::exec('INSERT INTO settings (`key`, `value`) VALUES (?, ?)', [$k, $v]);
                }
            }

            // Starter reference data: a head office department and the common approval types.
            if (!install_value('SELECT COUNT(*) FROM departments')) {
                Database::exec(
                    'INSERT INTO departments (name_ar, name_en, code, manager_id, active, sort_order) VALUES (?, ?, ?, NULL, 1, 1)',
                    ['الإدارة العامة', 'Head Office', 'HQ']
                );
            }
            $starterTypes = [
                ['اعتماد مهمة', 'Task Approval'],
                ['اعتماد طلب', 'Request Approval'],
                ['اعتماد خطاب', 'Correspondence Approval'],
                ['اعتماد صرف', 'Expense Approval'],
                ['اعتماد إجازة', 'Leave Approval'],
            ];
            if ((int) install_value('SELECT COUNT(*) FROM approval_types') === 0) {
                $sort = 1;
                foreach ($starterTypes as [$ar, $en]) {
                    Database::exec(
                        'INSERT INTO approval_types (name_ar, name_en, active, sort_order) VALUES (?, ?, 1, ?)',
                        [$ar, $en, $sort++]
                    );
                }
            }
            $starterCats = [
                ['مشاريع', 'Projects'],
                ['تقارير', 'Reports'],
                ['اجتماعات', 'Meetings'],
                ['مراسلات', 'Correspondence'],
            ];
            if ((int) install_value('SELECT COUNT(*) FROM task_categories') === 0) {
                $sort = 1;
                foreach ($starterCats as [$ar, $en]) {
                    Database::exec(
                        'INSERT INTO task_categories (name_ar, name_en, active, sort_order) VALUES (?, ?, 1, ?)',
                        [$ar, $en, $sort++]
                    );
                }
            }

            // Administrator — created once; a re-run refreshes the entered credentials.
            $existingAdmin = install_value('SELECT id FROM users WHERE username = ?', [$admin['username']]);
            $deptId = (int) install_value('SELECT id FROM departments ORDER BY id ASC LIMIT 1');
            if ($existingAdmin) {
                Database::exec(
                    'UPDATE users SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?',
                    [$admin['name'], $admin['email'], password_hash($admin['password'], PASSWORD_DEFAULT), 'admin', (int) $existingAdmin]
                );
            } elseif (!install_value('SELECT COUNT(*) FROM users WHERE email = ?', [$admin['email']])) {
                Database::exec(
                    'INSERT INTO users (name, name_en, username, email, password_hash, role, department_id, job_title, phone, manager_id, active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, ?)',
                    [
                        $admin['name'], '', $admin['username'], $admin['email'],
                        password_hash($admin['password'], PASSWORD_DEFAULT),
                        'admin', $deptId ?: null, 'مسؤول النظام', '', date('Y-m-d H:i:s'),
                    ]
                );
            }

            @mkdir(APP_ROOT . '/data', 0750, true);
            @mkdir(APP_ROOT . '/storage', 0750, true);
            @mkdir(APP_ROOT . '/storage/uploads', 0750, true);

            $configCode = "<?php\nreturn " . var_export([
                'db'       => $db,
                'timezone' => $tz,
            ], true) . ";\n";
            if (file_put_contents(CONFIG_FILE, $configCode) === false) {
                $errors[] = 'Could not write data/config.php — check folder permissions.';
            }

            // Recreate the web-server hardening file if the extractor skipped it.
            $htaccessPath = APP_ROOT . '/.htaccess';
            if (!is_file($htaccessPath) || filesize($htaccessPath) === 0) {
                @file_put_contents($htaccessPath, $htaccessRules . "\n");
            }
        } catch (Throwable $ex) {
            $errors[] = 'Database error: ' . $ex->getMessage();
        }
    }

    if (!$errors) {
        $_SESSION = [];
        session_destroy();
        header('Location: install.php?done=1');
        exit;
    }
}

$installed = isset($_GET['done']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · Idara — Manager Workspace</title>
<meta name="robots" content="noindex, nofollow">
<style>
:root { --bg:#f5f6fa; --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --primary:#4f46e5; --danger:#dc2626; --ok:#10b981; }
* { box-sizing:border-box; }
body { margin:0; background:var(--bg); color:var(--text); font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; display:flex; min-height:100vh; align-items:center; justify-content:center; padding:24px; }
.card { background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 10px 30px rgba(15,23,42,.08); padding:32px; width:100%; max-width:640px; }
h1 { margin:0 0 4px; font-size:22px; }
.sub { color:var(--muted); margin:0 0 22px; }
label { display:block; font-weight:600; margin:14px 0 6px; font-size:13.5px; }
input, select { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:9px; font-size:14px; background:#fff; color:inherit; }
input:focus, select:focus { outline:2px solid var(--primary); outline-offset:1px; border-color:var(--primary); }
.row { display:flex; gap:14px; } .row > div { flex:1; }
.seg { display:flex; gap:10px; } .seg label { margin:0; font-weight:600; }
.seg input { width:auto; margin-right:6px; }
.btn { display:inline-block; border:0; border-radius:10px; padding:12px 20px; font-size:15px; font-weight:600; cursor:pointer; background:var(--primary); color:#fff; text-decoration:none; }
.btn:hover { filter:brightness(1.08); }
.errors { background:#fef2f2; border:1px solid #fecaca; color:var(--danger); border-radius:10px; padding:12px 14px; margin-bottom:16px; }
.errors ul { margin:0; padding-left:18px; }
.ok-box { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; border-radius:10px; padding:14px; margin-bottom:16px; }
.mysql-fields { display:none; }
.note { color:var(--muted); font-size:13px; }
</style>
</head>
<body>
<div class="card">
  <h1>◈ Idara — Manager Workspace</h1>
  <p class="sub">Installer · step 1 of 1</p>

  <?php if ($installed): ?>
    <div class="ok-box"><strong>✅ Installation complete.</strong> Your administrator account is ready.</div>
    <a class="btn" href="index.php">Open Idara and sign in →</a>
    <p class="note" style="margin-top:18px">⚠️ <strong>Security:</strong> delete <code>install.php</code> from the server now. Also keep <code>data/</code> and <code>storage/</code> outside public web access (the included <code>.htaccess</code> already blocks them).</p>
  <?php else: ?>
    <?php if ($errors): ?>
      <div class="errors"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($configBroken): ?>
      <div class="errors">
        <strong>Existing configuration found, but it cannot connect:</strong><br>
        <?= htmlspecialchars($configError, ENT_QUOTES, 'UTF-8') ?><br>
        Completing this form below will repair the installation and rewrite <code>data/config.php</code>.
      </div>
    <?php elseif (isset($_GET['reconfigure'])): ?>
      <div class="errors" style="background:#fffbeb;border-color:#fde68a;color:#92400e">
        Reconfiguration mode — submitting this form will overwrite <code>data/config.php</code>.
      </div>
    <?php endif; ?>

    <form method="post" action="install.php" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['icsrf'] ??= bin2hex(random_bytes(32)), ENT_QUOTES, 'UTF-8') ?>">

      <label>Database</label>
      <div class="seg">
        <label><input type="radio" name="driver" value="sqlite" id="drv-sqlite" <?= ($old['driver'] ?? 'sqlite') !== 'mysql' ? 'checked' : '' ?>> SQLite — instant demo, zero configuration (recommended for first run)</label>
      </div>
      <div class="seg" style="margin-top:6px">
        <label><input type="radio" name="driver" value="mysql" id="drv-mysql" <?= ($old['driver'] ?? '') === 'mysql' ? 'checked' : '' ?>> MySQL — production (Hostinger: create a database in hPanel first)</label>
      </div>

      <div class="mysql-fields" id="mysql-fields">
        <div class="row">
          <div><label>MySQL host</label><input name="db_host" value="<?= htmlspecialchars($old['db_host'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>" placeholder="localhost"></div>
          <div><label>Port</label><input name="db_port" value="<?= htmlspecialchars($old['db_port'] ?? '3306', ENT_QUOTES, 'UTF-8') ?>"></div>
        </div>
        <div class="row">
          <div><label>Database name</label><input name="db_name" value="<?= htmlspecialchars($old['db_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
          <div><label>Database user</label><input name="db_user" value="<?= htmlspecialchars($old['db_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
        </div>
        <label>Database password</label>
        <input type="password" name="db_pass" value="">
      </div>

      <label>Site name</label>
      <input name="site_name" value="<?= htmlspecialchars($old['site_name'] ?? 'إدارة', ENT_QUOTES, 'UTF-8') ?>" placeholder="Investment Authority — Manager Workspace">
      <p class="note">Shown in the sidebar and page titles.</p>

      <label>Administrator account</label>
      <div class="row">
        <div><label>Full name</label><input name="admin_name" value="<?= htmlspecialchars($old['admin_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
        <div><label>Username</label><input name="admin_username" value="<?= htmlspecialchars($old['admin_username'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?>"></div>
      </div>
      <div class="row">
        <div><label>Email</label><input type="email" name="admin_email" value="<?= htmlspecialchars($old['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
        <div><label>Password (min 8 characters)</label><input type="password" name="admin_password" value=""></div>
      </div>

      <label>Timezone</label>
      <select name="timezone">
        <?php
        $tzList = ['Asia/Riyadh', 'Asia/Dubai', 'Europe/London', 'Europe/Berlin', 'America/New_York', 'UTC'];
        $sel = $old['timezone'] ?? 'Asia/Riyadh';
        foreach ($tzList as $t): ?>
          <option value="<?= $t ?>" <?= $sel === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>

      <p style="margin-top:22px"><button class="btn" type="submit">Install Idara</button></p>
    </form>

    <script>
    (function () {
      var s = document.getElementById('drv-sqlite'), m = document.getElementById('drv-mysql'), f = document.getElementById('mysql-fields');
      function sync() { f.style.display = m.checked ? 'block' : 'none'; }
      s.addEventListener('change', sync); m.addEventListener('change', sync); sync();
    })();
    </script>
  <?php endif; ?>
</div>
</body>
</html>
