<?php
declare(strict_types=1);

/**
 * Shared helpers: escaping, routing, flash messages, CSRF, settings,
 * job status maps, money/VAT maths, dates, audit log, notifications, uploads.
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

function job_statuses(): array
{
    return [
        'new'         => t('jstatus.new'),
        'scheduled'   => t('jstatus.scheduled'),
        'in_progress' => t('jstatus.in_progress'),
        'on_hold'     => t('jstatus.on_hold'),
        'done'        => t('jstatus.done'),
        'cancelled'   => t('jstatus.cancelled'),
    ];
}

function job_priorities(): array
{
    return [
        'low'    => t('priority.low'),
        'normal' => t('priority.normal'),
        'high'   => t('priority.high'),
        'urgent' => t('priority.urgent'),
    ];
}

function job_types(): array
{
    return ['one_time' => t('jtype.one_time'), 'contract' => t('jtype.contract')];
}

function contract_frequencies(): array
{
    return [
        'monthly'    => t('freq.monthly'),
        'quarterly'  => t('freq.quarterly'),
        'semiannual' => t('freq.semiannual'),
        'annual'     => t('freq.annual'),
    ];
}

function contract_statuses(): array
{
    return ['active' => t('cstatus.active'), 'expired' => t('cstatus.expired'), 'cancelled' => t('cstatus.cancelled')];
}

function invoice_statuses(): array
{
    return [
        'unpaid'    => t('istatus.unpaid'),
        'partial'   => t('istatus.partial'),
        'paid'      => t('istatus.paid'),
        'cancelled' => t('istatus.cancelled'),
    ];
}

function payment_methods(): array
{
    return [
        'cash'     => t('pmethod.cash'),
        'card'     => t('pmethod.card'),
        'transfer' => t('pmethod.transfer'),
        'cheque'   => t('pmethod.cheque'),
    ];
}

function customer_types(): array
{
    return ['company' => t('ctype.company'), 'individual' => t('ctype.individual')];
}

function roles(): array
{
    return [
        'technician' => t('role.technician'),
        'dispatcher' => t('role.dispatcher'),
        'accountant' => t('role.accountant'),
        'admin'      => t('role.admin'),
    ];
}

/** Open statuses are "work still to do". */
function is_open_job(?string $status): bool
{
    return in_array($status, ['new', 'scheduled', 'in_progress', 'on_hold'], true);
}

// ---------------------------------------------------------------- money & dates

function money(float $amount, bool $withCurrency = true): string
{
    $formatted = number_format($amount, 2, '.', ',');
    return $withCurrency ? $formatted . ' ' . (string) setting('currency', 'AED') : $formatted;
}

function vat_rate(): float
{
    return (float) setting('vat_rate', '5');
}

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

function fmt_time(?string $ts): string
{
    return $ts ? substr((string) $ts, 0, 5) : '';
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

/** Whole days from today until a date (negative = in the past). */
function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $today = new DateTimeImmutable(date('Y-m-d'));
    $target = new DateTimeImmutable(date('Y-m-d', strtotime($date)));
    return (int) $today->diff($target)->format('%r%a');
}

/** late | today | soon | ok (null when no date). */
function due_state(?string $date, int $soonDays = 7): ?string
{
    $days = days_until($date);
    if ($days === null) {
        return null;
    }
    if ($days < 0) {
        return 'late';
    }
    if ($days === 0) {
        return 'today';
    }
    if ($days <= $soonDays) {
        return 'soon';
    }
    return 'ok';
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

function notify(?int $userId, string $message, ?string $link = null): void
{
    if (!$userId) {
        return;
    }
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

// ---------------------------------------------------------------- uploads (job photos / documents)

function handle_job_uploads(int $jobId, int $uploaderId, ?int $noteId = null): array
{
    $out = ['ok' => 0, 'errors' => []];
    if (empty($_FILES['files']['name'])) {
        return $out;
    }
    $f = $_FILES['files'];
    if (is_string($f['name'])) {
        $f = [
            'name'     => [$f['name']],
            'type'     => [$f['type']],
            'tmp_name' => [$f['tmp_name']],
            'error'    => [$f['error']],
            'size'     => [$f['size']],
        ];
    }
    $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
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
            $out['errors'][] = e((string) $name) . ' — max 5 MB';
            continue;
        }
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $out['errors'][] = e((string) $name) . ' — file type not allowed';
            continue;
        }
        $dir = APP_ROOT . '/storage/uploads/' . $jobId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $stored = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'][$i], $dir . '/' . $stored)) {
            $out['errors'][] = 'Could not store ' . e((string) $name);
            continue;
        }
        Database::exec(
            'INSERT INTO job_files (job_id, note_id, original_name, stored_name, mime, size, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $jobId, $noteId, (string) $name, $stored,
                (string) ($f['type'][$i] ?: 'application/octet-stream'),
                (int) $f['size'][$i], $uploaderId, now(),
            ]
        );
        audit('job_file_added', 'job', $jobId, 'file=' . (string) $name);
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
