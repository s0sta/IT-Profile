<?php
declare(strict_types=1);

/**
 * Shared helpers: escaping, routing, flash messages, CSRF, settings,
 * status/priority maps, SLA math, audit log, notifications, uploads, dates.
 */

// ---------------------------------------------------------------- output / flow

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function u(string $path = ''): string
{
    return 'index.php?p=' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . u($path));
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------- CSRF

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $given = $_POST['csrf'] ?? '';
    if (!is_string($given) || !hash_equals(csrf_token(), $given)) {
        http_response_code(403);
        exit('Invalid or expired security token. Please go back and try again.');
    }
}

// ---------------------------------------------------------------- settings

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Database::all('SELECT `key`, `value` FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function save_setting(string $key, string $value): void
{
    $exists = Database::value('SELECT COUNT(*) FROM settings WHERE `key` = ?', [$key]);
    if ($exists) {
        Database::exec('UPDATE settings SET `value` = ? WHERE `key` = ?', [$value, $key]);
    } else {
        Database::exec('INSERT INTO settings (`key`, `value`) VALUES (?, ?)', [$key, $value]);
    }
}

// ---------------------------------------------------------------- domain maps

function statuses(): array
{
    return [
        'new'         => t('status.new'),
        'open'        => t('status.open'),
        'in_progress' => t('status.in_progress'),
        'waiting'     => t('status.waiting'),
        'resolved'    => t('status.resolved'),
        'closed'      => t('status.closed'),
    ];
}

function priorities(): array
{
    return [
        'low'    => t('priority.low'),
        'medium' => t('priority.medium'),
        'high'   => t('priority.high'),
        'urgent' => t('priority.urgent'),
    ];
}

function roles(): array
{
    return ['user' => t('role.user'), 'agent' => t('role.agent'), 'admin' => t('role.admin')];
}

function is_open_status(?string $status): bool
{
    return $status !== null && !in_array($status, ['resolved', 'closed'], true);
}

// ---------------------------------------------------------------- SLA

function sla_policy(string $priority): array
{
    static $policies = null;
    if ($policies === null) {
        $policies = [];
        foreach (Database::all('SELECT * FROM sla') as $row) {
            $policies[$row['priority']] = $row;
        }
    }
    return $policies[$priority] ?? ['response_hours' => 24, 'resolution_hours' => 120];
}

function sla_due(string $createdAt, string $priority): string
{
    $hours = (float) sla_policy($priority)['response_hours'];
    return date('Y-m-d H:i:s', strtotime($createdAt) + (int) round($hours * 3600));
}

function sla_state(array $ticket): string
{
    // ok | warning | breached — response SLA for open tickets
    if (!is_open_status($ticket['status'] ?? null)) {
        return $ticket['resolved_at'] ? 'ok' : 'ok';
    }
    if (!empty($ticket['first_response_at'])) {
        return 'ok';
    }
    if (!empty($ticket['sla_due']) && strtotime((string) $ticket['sla_due']) < time()) {
        return 'breached';
    }
    return 'ok';
}

// ---------------------------------------------------------------- dates

function now(): string
{
    return date('Y-m-d H:i:s');
}

function fmt_dt(?string $ts): string
{
    return $ts ? date('Y-m-d H:i', strtotime($ts)) : '—';
}

function fmt_date(?string $ts): string
{
    return $ts ? date('Y-m-d', strtotime($ts)) : '—';
}

function time_ago(?string $ts): string
{
    if (!$ts) {
        return '—';
    }
    $diff = time() - strtotime($ts);
    if ($diff < 60) {
        return t('common.just_now');
    }
    if ($diff < 3600) {
        return t('common.minutes_ago', ['n' => (int) floor($diff / 60)]);
    }
    if ($diff < 86400) {
        return t('common.hours_ago', ['n' => (int) floor($diff / 3600)]);
    }
    if ($diff < 604800) {
        return t('common.days_ago', ['n' => (int) floor($diff / 86400)]);
    }
    return date('M j, Y', strtotime($ts));
}

// ---------------------------------------------------------------- audit + notify

function audit(string $action, ?string $entity = null, $entityId = null, ?string $details = null): void
{
    Database::exec(
        'INSERT INTO audit_log (user_id, username, action, entity, entity_id, details, ip, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            Auth::id() ?: null,
            (Auth::current()['name'] ?? null) ?: 'System',
            $action,
            $entity,
            $entityId === null ? null : (string) $entityId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            now(),
        ]
    );
}

function notify(int $userId, string $message, ?string $link = null): void
{
    Database::exec(
        'INSERT INTO notifications (user_id, message, link, created_at) VALUES (?, ?, ?, ?)',
        [$userId, $message, $link, now()]
    );
}

// ---------------------------------------------------------------- text

function render_body(?string $text): string
{
    return nl2br(e($text));
}

function initials(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $name);
    $ini = strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $ini .= strtoupper(mb_substr(end($parts), 0, 1));
    }
    return $ini;
}

function excerpt(?string $text, int $len = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len - 1) . '…';
}

// ---------------------------------------------------------------- uploads

function handle_uploads(int $ticketId, ?int $replyId, int $uploaderId): array
{
    $out = ['ok' => 0, 'errors' => []];
    if (empty($_FILES['attachments']['name'])) {
        return $out;
    }
    $f = $_FILES['attachments'];
    if (is_string($f['name'])) { // normalize a single file to array shape
        $f = [
            'name'     => [$f['name']],
            'type'     => [$f['type']],
            'tmp_name' => [$f['tmp_name']],
            'error'    => [$f['error']],
            'size'     => [$f['size']],
        ];
    }
    $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
    $max = 5 * 1024 * 1024; // 5 MB

    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($f['error'][$i] !== UPLOAD_ERR_OK) {
            $out['errors'][] = 'Upload failed for ' . e($name);
            continue;
        }
        if ($f['size'][$i] > $max) {
            $out['errors'][] = e($name) . ' exceeds the 5 MB limit';
            continue;
        }
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $out['errors'][] = e($name) . ': file type not allowed';
            continue;
        }
        $dir = APP_ROOT . '/storage/uploads/' . $ticketId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $stored = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'][$i], $dir . '/' . $stored)) {
            $out['errors'][] = 'Failed to store ' . e($name);
            continue;
        }
        Database::exec(
            'INSERT INTO attachments (ticket_id, reply_id, original_name, stored_name, mime, size, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $ticketId, $replyId, (string) $name, $stored,
                (string) ($f['type'][$i] ?: 'application/octet-stream'),
                (int) $f['size'][$i], $uploaderId, now(),
            ]
        );
        $out['ok']++;
    }
    return $out;
}

// ---------------------------------------------------------------- misc

function paginate(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = max(1, min($page, $pages));
    return [$page, $pages];
}

function keep_query(array $except = []): string
{
    $qs = $_GET;
    unset($qs['p'], $qs['page']);
    foreach ($except as $k) {
        unset($qs[$k]);
    }
    return http_build_query($qs);
}

/**
 * True when a column exists — lets the app run both before and after a schema
 * upgrade (SQLite and MySQL/MariaDB). Result is cached per request.
 */
function column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $found = false;
    try {
        $driver = (string) Database::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            foreach (Database::all('PRAGMA table_info(' . $table . ')') as $row) {
                if (($row['name'] ?? '') === $column) {
                    $found = true;
                    break;
                }
            }
        } else {
            $found = (bool) Database::value(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );
        }
    } catch (Throwable $e) {
        $found = false;
    }
    return $cache[$key] = $found;
}
