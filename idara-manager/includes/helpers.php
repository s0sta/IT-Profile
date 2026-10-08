<?php
declare(strict_types=1);

/**
 * Idara — shared helpers: output, routing, flash, CSRF, settings, audit,
 * notifications, uploads, dates and the domain vocabulary (tasks, approvals,
 * correspondence, meetings).
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

// ---------------------------------------------------------------- domain vocabulary

function task_statuses(): array
{
    return [
        'new'         => t('tstatus.new'),
        'in_progress' => t('tstatus.in_progress'),
        'waiting'     => t('tstatus.waiting'),
        'blocked'     => t('tstatus.blocked'),
        'completed'   => t('tstatus.completed'),
        'cancelled'   => t('tstatus.cancelled'),
    ];
}

function task_is_open(?string $status): bool
{
    return $status !== null && !in_array($status, ['completed', 'cancelled'], true);
}

function approval_statuses(): array
{
    return [
        'pending'   => t('astatus.pending'),
        'approved'  => t('astatus.approved'),
        'rejected'  => t('astatus.rejected'),
        'returned'  => t('astatus.returned'),
        'cancelled' => t('astatus.cancelled'),
    ];
}

function step_statuses(): array
{
    return [
        'waiting'  => t('sstatus.waiting'),
        'pending'  => t('sstatus.pending'),
        'approved' => t('sstatus.approved'),
        'rejected' => t('sstatus.rejected'),
        'returned' => t('sstatus.returned'),
        'skipped'  => t('sstatus.skipped'),
    ];
}

function correspondence_statuses(): array
{
    return [
        'new'          => t('cstatus.new'),
        'under_review' => t('cstatus.under_review'),
        'replied'      => t('cstatus.replied'),
        'archived'     => t('cstatus.archived'),
    ];
}

function meeting_statuses(): array
{
    return [
        'scheduled' => t('mstatus.scheduled'),
        'done'      => t('mstatus.done'),
        'cancelled' => t('mstatus.cancelled'),
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
    return [
        'admin'     => t('role.admin'),
        'executive' => t('role.executive'),
        'manager'   => t('role.manager'),
        'member'    => t('role.member'),
    ];
}

function directions(): array
{
    return ['incoming' => t('dir.incoming'), 'outgoing' => t('dir.outgoing')];
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
    return date('Y-m-d', strtotime($ts));
}

/** Whole days between today and a date (negative = overdue). */
function days_left(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $today = new DateTimeImmutable(date('Y-m-d'));
    $target = new DateTimeImmutable(substr($date, 0, 10));
    return (int) $today->diff($target)->format('%r%a');
}

function is_overdue(?string $date, ?string $status = null): bool
{
    if (!$date || ($status !== null && !task_is_open($status))) {
        return false;
    }
    return days_left($date) < 0;
}

function due_label(?string $date, ?string $status = null): string
{
    if (!$date) {
        return '—';
    }
    $days = days_left($date);
    if ($days === null) {
        return fmt_date($date);
    }
    if ($days < 0 && ($status === null || task_is_open($status))) {
        return t('common.overdue_by', ['n' => abs($days)]);
    }
    if ($days === 0) {
        return t('common.due_today');
    }
    if ($days === 1) {
        return t('common.due_tomorrow');
    }
    if ($days <= 14) {
        return t('common.due_in', ['n' => $days]);
    }
    return fmt_date($date);
}

// ---------------------------------------------------------------- user display

/** Display name in the current language (English prefers name_en). */
function user_name(array $u): string
{
    if (daem_current_lang() !== 'ar' && !empty($u['name_en']) && trim((string) $u['name_en']) !== '') {
        return (string) $u['name_en'];
    }
    return (string) ($u['name'] ?? '—');
}

/** A job title is single-language (Arabic); hide it in English mode when it is Arabic-only. */
function job_title_display(?string $title): string
{
    $title = trim((string) $title);
    if ($title === '') {
        return '—';
    }
    if (daem_current_lang() !== 'ar' && preg_match('/[\x{0600}-\x{06FF}]/u', $title)) {
        return '—';
    }
    return $title;
}

/** True when a task/correspondence status means the item is closed (no due date shown). */
function is_closed_status(?string $status): bool
{
    return in_array($status, ['completed', 'cancelled', 'archived'], true);
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
    $parts = preg_split('/\s+/', $name) ?: [];
    $ini = mb_substr($parts[0], 0, 1, 'UTF-8');
    if (count($parts) > 1) {
        $ini .= mb_substr((string) end($parts), 0, 1, 'UTF-8');
    }
    return mb_strtoupper($ini, 'UTF-8');
}

function excerpt(?string $text, int $len = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if (mb_strlen($text, 'UTF-8') <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len - 1, 'UTF-8') . '…';
}

// ---------------------------------------------------------------- uploads

/**
 * Store uploaded files. Any of task_id / update_id / approval_id may be null.
 * Allowed: pdf, png, jpg, jpeg, gif, txt, csv, doc, docx, xls, xlsx, zip (max 5 MB).
 */
function handle_uploads(?int $taskId, ?int $updateId, ?int $approvalId, int $uploaderId): array
{
    $out = ['ok' => 0, 'errors' => []];
    if (empty($_FILES['attachments']['name'])) {
        return $out;
    }
    $f = $_FILES['attachments'];
    if (is_string($f['name'])) {
        $f = [
            'name'     => [$f['name']],
            'type'     => [$f['type']],
            'tmp_name' => [$f['tmp_name']],
            'error'    => [$f['error']],
            'size'     => [$f['size']],
        ];
    }
    $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
    $max = 5 * 1024 * 1024;

    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($f['error'][$i] !== UPLOAD_ERR_OK) {
            $out['errors'][] = 'Upload failed for ' . e((string) $name);
            continue;
        }
        if ($f['size'][$i] > $max) {
            $out['errors'][] = e((string) $name) . ' ' . t('common.too_big');
            continue;
        }
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $out['errors'][] = e((string) $name) . ' ' . t('common.type_not_allowed');
            continue;
        }
        $bucket = $taskId ?: ($approvalId ? 'approvals' : 'misc');
        $dir = APP_ROOT . '/storage/uploads/' . $bucket;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $stored = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'][$i], $dir . '/' . $stored)) {
            $out['errors'][] = t('common.upload_failed');
            continue;
        }
        Database::exec(
            'INSERT INTO attachments (task_id, update_id, approval_id, original_name, stored_name, mime, size, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $taskId, $updateId, $approvalId, (string) $name, $stored,
                (string) ($f['type'][$i] ?: 'application/octet-stream'),
                (int) $f['size'][$i], $uploaderId, now(),
            ]
        );
        $out['ok']++;
    }
    return $out;
}

function attachmentsFor(?int $taskId = null, ?int $updateId = null, ?int $approvalId = null): array
{
    if ($taskId !== null) {
        if ($updateId !== null) {
            return Database::all('SELECT * FROM attachments WHERE update_id = ? ORDER BY id ASC', [$updateId]);
        }
        return Database::all('SELECT * FROM attachments WHERE task_id = ? AND update_id IS NULL ORDER BY id ASC', [$taskId]);
    }
    if ($approvalId !== null) {
        return Database::all('SELECT * FROM attachments WHERE approval_id = ? ORDER BY id ASC', [$approvalId]);
    }
    return [];
}

function attachment_path(string $bucket, string $stored): string
{
    return APP_ROOT . '/storage/uploads/' . $bucket . '/' . $stored;
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

/** Pick the Arabic or English label of a bilingual row. */
function bilingual(array $row, string $field): string
{
    $ar = (string) ($row[$field . '_ar'] ?? '');
    $en = (string) ($row[$field . '_en'] ?? '');
    if (daem_current_lang() === 'ar') {
        return $ar !== '' ? $ar : $en;
    }
    return $en !== '' ? $en : $ar;
}

/** Task progress colour band: low / mid / high / done */
function progress_band(int $progress): string
{
    if ($progress >= 100) {
        return 'done';
    }
    if ($progress >= 60) {
        return 'high';
    }
    if ($progress >= 30) {
        return 'mid';
    }
    return 'low';
}
