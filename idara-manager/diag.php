<?php
declare(strict_types=1);

/**
 * Idara — deployment self-check (TEMPORARY).
 * Open:  https://your-domain/diag.php?go=1
 * DELETE THIS FILE from the server as soon as you have the output.
 */

if (($_GET['go'] ?? '') !== '1') {
    exit('Add ?go=1 to the URL to run the diagnostics.');
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

$root = __DIR__;

echo '<pre style="font:12.5px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;padding:18px;background:#0f172a;color:#e2e8f0;white-space:pre-wrap">';
echo "IDARA SELF-CHECK — " . date('c') . "\n";
echo "PHP " . PHP_VERSION . " · SAPI " . PHP_SAPI . " · " . (PHP_OS_FAMILY) . "\n\n";

echo "--- EXTENSIONS ---\n";
foreach (['pdo', 'pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl', 'session', 'json', 'fileinfo'] as $ext) {
    printf("%-12s %s\n", $ext, extension_loaded($ext) ? 'OK' : 'MISSING');
}

echo "\n--- FILE INTEGRITY (vs released build) ---\n";
$manifestFile = $root . '/integrity.php';
$manifest = is_file($manifestFile) ? require $manifestFile : null;
if (!is_array($manifest)) {
    echo "integrity.php MISSING — cannot verify the uploaded files\n";
} else {
    $bad = 0;
    foreach ($manifest as $rel => $meta) {
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            echo "MISSING   {$rel}\n";
            $bad++;
            continue;
        }
        if (md5_file($path) !== $meta['md5']) {
            printf("MISMATCH  %s  (server %dB / expected %dB)\n", $rel, filesize($path), $meta['size']);
            $bad++;
        }
    }
    echo $bad === 0
        ? 'All ' . count($manifest) . " files match the released build ✅\n"
        : "{$bad} problem file(s) ❌  → re-extract the ZIP and retry\n";
}

echo "\n--- CONFIG + DATABASE ---\n";
$cfgFile = $root . '/data/config.php';
echo 'data/config.php: ' . (is_file($cfgFile) ? 'exists (' . filesize($cfgFile) . ' bytes)' : 'MISSING') . "\n";

if (is_file($cfgFile)) {
    try {
        $cfg = require $cfgFile;
        if (!is_array($cfg) || !isset($cfg['db']) || !is_array($cfg['db'])) {
            echo "config content INVALID — expected an array with a 'db' key\n";
        } else {
            $db = $cfg['db'];
            printf(
                "driver=%s name=%s user=%s passLength=%d\n",
                $db['driver'] ?? '?',
                $db['name'] ?? '-',
                $db['user'] ?? '-',
                strlen((string) ($db['pass'] ?? ''))
            );
            require_once $root . '/includes/db.php';
            Database::init($db);
            echo "connection: OK\n";

            if (($db['driver'] ?? '') === 'mysql') {
                $tables = Database::all(
                    'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name',
                    [$db['name']]
                );
                echo 'tables (' . count($tables) . '): ' . implode(', ', array_column($tables, 't')) . "\n";
            }
            foreach (['users', 'departments', 'tasks', 'approvals', 'approval_steps', 'correspondence', 'meetings', 'settings'] as $t) {
                try {
                    echo "  count({$t}) = " . Database::value("SELECT COUNT(*) FROM `{$t}`") . "\n";
                } catch (Throwable $e) {
                    echo "  {$t} ERROR: " . $e->getMessage() . "\n";
                }
            }
        }
    } catch (Throwable $e) {
        echo 'DATABASE ERROR: ' . get_class($e) . ': ' . $e->getMessage() . "\n"
            . '  at ' . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

echo "\n--- WRITABLE FOLDERS ---\n";
foreach (['data', 'storage', 'storage/uploads'] as $dir) {
    $p = $root . '/' . $dir;
    printf("%-18s %s\n", $dir, is_dir($p) ? (is_writable($p) ? 'writable' : 'NOT writable') : 'MISSING');
}

echo "\n--- SESSION ---\n";
try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('DAEMDIAG');
        session_start();
    }
    echo "session start: OK (save_path=" . session_save_path() . ")\n";
} catch (Throwable $e) {
    echo 'session ERROR: ' . $e->getMessage() . "\n";
}

echo "\n--- APP BOOTSTRAP ---\n";
try {
    ob_start();
    require $root . '/includes/bootstrap.php';
    ob_end_clean();
    echo "bootstrap.php loaded: OK\n";
} catch (Throwable $e) {
    echo 'BOOTSTRAP ERROR: ' . get_class($e) . ': ' . $e->getMessage() . "\n"
        . '  at ' . $e->getFile() . ':' . $e->getLine() . "\n";
}

echo "\n--- LAST PHP ERROR (if any) ---\n";
$err = error_get_last();
echo $err ? ($err['message'] . ' @ ' . $err['file'] . ':' . $err['line'] . "\n") : "none\n";

echo "\n⛔ DELETE diag.php FROM THE SERVER NOW.\n";
echo '</pre>';
