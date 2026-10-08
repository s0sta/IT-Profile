<?php
declare(strict_types=1);

/**
 * Daem — IT Help Desk Platform
 * Bootstrap: loads configuration, starts the hardened session, initialises the
 * database and authentication. Every page is loaded through index.php.
 */

define('APP_ROOT', dirname(__DIR__));
define('CONFIG_FILE', APP_ROOT . '/data/config.php');

if (!is_file(CONFIG_FILE)) {
    header('Location: install.php');
    exit;
}

$config = require CONFIG_FILE;

// ---- Error handling: log the details, never show a blank 500 ------------
/** Write a timestamped line to data/error.log (best effort). */
$daemLog = static function (string $line): void {
    $file = APP_ROOT . '/data/error.log';
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
};

set_exception_handler(static function (Throwable $e) use ($daemLog): void {
    $line = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    @error_log('[Daem] ' . $line);
    $daemLog($line);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Server error</title>'
        . '<style>body{font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;background:#f5f6fa;color:#0f172a;'
        . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
        . '.c{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:32px;max-width:540px;'
        . 'box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center}'
        . 'h1{margin:0 0 8px;font-size:20px}p{color:#64748b;margin:0 0 6px}code{background:#f1f5f9;padding:2px 6px;border-radius:5px}'
        . 'a{color:#4f46e5;font-weight:600}</style></head><body><div class="c">'
        . '<h1>⚠️ Something went wrong</h1>'
        . '<p>The error has been logged server-side.</p>'
        . '<p>Administrator: check <code>data/error.log</code> or your hosting PHP error log for the exact message.</p>'
        . '<p>If the database connection failed, open <a href="install.php?reconfigure=1">install.php?reconfigure=1</a> to repair the configuration.</p>'
        . '<p style="margin-top:16px"><a href="index.php">Try again</a></p></div></body></html>';
    exit;
});
register_shutdown_function(static function () use ($daemLog): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $line = 'FATAL: ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line'];
        @error_log('[Daem] ' . $line);
        $daemLog($line);
    }
});

// ---- Hardened session -----------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
    session_name('DAEMSESS');
    session_start();
}

require_once APP_ROOT . '/includes/i18n.php';

// ---- Language: ?lang=xx wins, then session, then cookie, then English ----
if (isset($_GET['lang'])) {
    daem_set_lang((string) $_GET['lang']);
    $query = $_GET;
    unset($query['lang']);
    $target = strtok((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'), '?') ?: 'index.php';
    if ($query) {
        $target .= '?' . http_build_query($query);
    }
    header('Location: ' . $target);
    exit;
}

require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/models.php';
require_once APP_ROOT . '/includes/auth.php';

Database::init($config['db']);

date_default_timezone_set($config['timezone'] ?? 'Asia/Riyadh');

Auth::boot();
