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

// ---------------------------------------------------------------- security helpers

/** Password policy: at least 10 chars, containing letters and digits. */
function valid_password(string $pw): bool
{
    return mb_strlen($pw, 'UTF-8') >= 10
        && preg_match('/\p{L}/u', $pw) === 1
        && preg_match('/\d/', $pw) === 1;
}

/** New base32 secret for TOTP (RFC 4648 alphabet, 20 random bytes). */
function totp_secret(): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bytes = random_bytes(20);
    $out = '';
    $buffer = 0;
    $bits = 0;
    foreach (str_split($bytes) as $chr) {
        $buffer = ($buffer << 8) | ord($chr);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $alphabet[($buffer >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= $alphabet[($buffer << (5 - $bits)) & 31];
    }
    return $out;
}

/** RFC 6238 TOTP check with a ±1 time-step window. */
function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }
    $base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    $bin = '';
    foreach (str_split($secret) as $chr) {
        $val = strpos($base32, $chr);
        if ($val === false) {
            return false;
        }
        $bin .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bin, 8) as $chunk) {
        if (strlen($chunk) < 8) {
            $chunk = str_pad($chunk, 8, '0');
        }
        $bytes .= chr(bindec($chunk));
    }
    $time = (int) floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $counter = pack('N*', $time + $i);
        $hash = hash_hmac('sha1', $counter, $bytes, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
               | ((ord($hash[$offset + 1]) & 0xFF) << 16)
               | ((ord($hash[$offset + 2]) & 0xFF) << 8)
               | (ord($hash[$offset + 3]) & 0xFF);
        if (str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT) === $code) {
            return true;
        }
    }
    return false;
}

/**
 * Hijri date (Umm al-Qura) via ext-intl; falls back to the Gregorian date
 * when intl is unavailable. Returns e.g. "1448/05/12".
 */
function hijri_date(string $ymd): string
{
    if (!class_exists('IntlDateFormatter')) {
        return '';
    }
    $ts = strtotime($ymd);
    if ($ts === false) {
        return '';
    }
    $fmt = new IntlDateFormatter(
        'ar-SA@calendar=islamic-umalqura',
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        'Asia/Riyadh',
        IntlDateFormatter::TRADITIONAL,
        'yyyy/MM/dd'
    );
    $h = $fmt->format($ts);
    if (!is_string($h)) {
        return '';
    }
    // In English mode render Latin digits (the ar-SA formatter outputs
    // Arabic-Indic digits 0-9).
    if (daem_current_lang() !== 'ar') {
        $map = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];
        $h = strtr($h, $map);
    }
    return $h;
}

/** Gregorian + Hijri pair for print headers, e.g. "2026/10/10 · 1448/04/19 هـ". */
function both_dates(?string $ymd): string
{
    $ymd = substr((string) $ymd, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return '';
    }
    $g = str_replace('-', '/', $ymd);
    $h = hijri_date($ymd);
    return $h !== '' ? $g . ' · ' . $h . ' ' . t('print.hijri_suffix') : $g;
}

// ---------------------------------------------------------------- email transport

/**
 * Send an email through the configured SMTP settings; falls back to PHP
 * mail() when no SMTP host is configured. Never throws: failures are logged
 * to data/error.log and the request continues.
 */
function send_email(string $to, string $subject, string $body): void
{
    $enabled = setting('smtp_enabled', '0') === '1';
    if (!$enabled) {
        return;
    }
    $from = setting('smtp_from', 'noreply@' . (isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost'));
    $host = setting('smtp_host', '');
    $port = (int) setting('smtp_port', '587');
    $user = setting('smtp_user', '');
    $pass = setting('smtp_pass', '');

    $headers = "From: " . setting('site_name', 'Idara') . " <{$from}>
"
             . "MIME-Version: 1.0
"
             . "Content-Type: text/plain; charset=UTF-8
";

    try {
        if ($host !== '') {
            $errno = 0;
            $errstr = '';
            $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10);
            if (!$fp) {
                throw new RuntimeException("SMTP connect failed: {$errstr}");
            }
            $read = static function () use ($fp): string {
                $data = '';
                while ($line = fgets($fp, 515)) {
                    $data .= $line;
                    if (isset($line[3]) && $line[3] === ' ') {
                        break;
                    }
                }
                return $data;
            };
            $cmd = static function (string $line) use ($fp): void {
                fwrite($fp, $line . "
");
            };
            $read();
            $cmd('EHLO ' . (isset($_SERVER['SERVER_NAME']) ? (string) $_SERVER['SERVER_NAME'] : 'localhost'));
            while (str_starts_with($resp = $read(), '250-')) {
                // consume multiline EHLO
            }
            if ($port === 465) {
                stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            } else {
                $cmd('STARTTLS');
                $read();
                stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $cmd('EHLO ' . (isset($_SERVER['SERVER_NAME']) ? (string) $_SERVER['SERVER_NAME'] : 'localhost'));
                $read();
            }
            if ($user !== '') {
                $cmd('AUTH LOGIN');
                $read();
                $cmd(base64_encode($user));
                $read();
                $cmd(base64_encode($pass));
                $read();
            }
            $cmd('MAIL FROM:<' . $from . '>');
            $read();
            $cmd('RCPT TO:<' . $to . '>');
            $read();
            $cmd('DATA');
            $read();
            $cmd('Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'));
            $cmd($headers . "
" . str_replace("
.", "
..", $body));
            $cmd('.');
            $read();
            $cmd('QUIT');
            fclose($fp);
        } else {
            @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers);
        }
    } catch (Throwable $e) {
        error_log('[mail] ' . $e->getMessage());
    }
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
    // Optional email transport (no-op until enabled in Admin → Settings).
    if (setting('smtp_enabled', '0') === '1') {
        $user = Users::find($userId);
        if ($user && !empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            send_email(
                (string) $user['email'],
                setting('site_name', t('app.name')) . ': ' . strip_tags($message),
                $message . ($link !== null ? "

" . 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/' . ltrim($link, '/') : '')
            );
        }
    }
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
        $base = APP_ROOT . '/storage/uploads';
        $dir = $base . '/' . $bucket;
        // Create the folder if needed and make sure it is really writable before moving the file.
        foreach ([$base, $dir] as $candidate) {
            if (!is_dir($candidate)) {
                @mkdir($candidate, 0755, true);
                if (!is_dir($candidate)) {
                    @mkdir($candidate, 0775, true);
                }
            }
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $out['errors'][] = e((string) $name) . ' — ' . t('common.storage_warning', ['folders' => 'storage/uploads/' . $bucket]);
            continue;
        }
        $stored = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'][$i], $dir . '/' . $stored)) {
            $out['errors'][] = e((string) $name) . ' — ' . t('common.upload_failed');
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
    // Most specific first: an update attachment is looked up by its own id and
    // must also work when no task id is passed (that was the bug: the function
    // returned [] for attachmentsFor(null, $updateId), so files were stored but
    // never shown).
    if ($updateId !== null) {
        return Database::all('SELECT * FROM attachments WHERE update_id = ? ORDER BY id ASC', [$updateId]);
    }
    if ($taskId !== null) {
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

/**
 * Which storage folders are missing or not writable? Empty array = all good.
 * Used by the admin settings page so a broken folder is visible in the UI
 * instead of silently swallowing every upload.
 */
function storage_problems(): array
{
    $problems = [];
    foreach (['storage', 'storage/uploads', 'storage/avatars'] as $rel) {
        $path = APP_ROOT . '/' . $rel;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        if (!is_dir($path)) {
            $problems[] = $rel . ' (missing)';
        } elseif (!is_writable($path)) {
            $problems[] = $rel . ' (not writable)';
        }
    }
    return $problems;
}

// ---------------------------------------------------------------- misc

function paginate(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = max(1, min($page, $pages));
    return [$page, $pages];
}

/**
 * Build a pagination link for a route while keeping the active filters.
 * keep_query() returns '' when nothing is filtered, so appending it blindly
 * produced links like "tasks&&page=2" — this joins the parts correctly.
 */
function page_url(string $route, int $page): string
{
    $qs = keep_query();
    return u($route . ($qs !== '' ? '&' . $qs : '') . '&page=' . max(1, (int) $page));
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
