<?php
declare(strict_types=1);

/**
 * Idara — serve an attachment: ?p=download&id=N
 *
 * Access: admin/executive, the uploader, or anyone who may view the related
 * task / approval. The storage folder mirrors handle_uploads() in helpers.php:
 * <task id> | approvals | misc.
 */

$me = Auth::current();
if (!$me) {
    http_response_code(403);
    exit(t('auth.denied'));
}

// Profile photos: ?p=download&avatar=<filename> — served from storage/avatars.
if (isset($_GET['avatar']) && $_GET['avatar'] !== '') {
    $av = basename((string) $_GET['avatar']);
    if (!preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp|gif)$/i', $av)) {
        http_response_code(404);
        exit(t('e404.text'));
    }
    $f = APP_ROOT . '/storage/avatars/' . $av;
    if (!is_file($f)) {
        http_response_code(404);
        exit(t('e404.text'));
    }
    $ext = strtolower(pathinfo($av, PATHINFO_EXTENSION));
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext];
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($f));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($f);
    exit;
}

$id  = (int) ($_GET['id'] ?? 0);
$att = $id > 0 ? Database::one('SELECT * FROM attachments WHERE id = ?', [$id]) : null;
if (!$att) {
    http_response_code(404);
    exit(t('e404.text'));
}

$isOverseer = in_array($me['role'], ['admin', 'executive'], true);
$allowed    = $isOverseer || (int) $att['uploaded_by'] === (int) $me['id'];

if (!$allowed && !empty($att['task_id'])) {
    $task    = Tasks::find((int) $att['task_id']);
    $allowed = $task !== null && Tasks::canView($task, $me);
}
if (!$allowed && !empty($att['approval_id'])) {
    $approval = Approvals::find((int) $att['approval_id']);
    $allowed  = $approval !== null && Approvals::canView($approval, $me);
}
if (!$allowed) {
    http_response_code(403);
    exit(t('auth.denied'));
}

// Resolve the stored file exactly like handle_uploads() does.
$bucket = !empty($att['task_id'])
    ? (string) (int) $att['task_id']
    : (!empty($att['approval_id']) ? 'approvals' : 'misc');
$stored = basename((string) $att['stored_name']);
$file   = attachment_path($bucket, $stored);

if ($stored === '' || !is_file($file)) {
    http_response_code(404);
    exit(t('e404.text'));
}

// Never echo client-supplied bytes into a header: whitelist the MIME shape and
// strip CR/LF/quote characters from the download name.
$mime = (string) ($att['mime'] ?? '');
if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9.+-]*/[A-Za-z0-9][A-Za-z0-9.+-]*$#', $mime)) {
    $mime = 'application/octet-stream';
}

$name = str_replace(["\r", "\n", '"', '\\', '/'], '', basename((string) $att['original_name']));
if ($name === '') {
    $name = 'attachment';
}
$fallback = preg_replace('/[^\x20-\x7E]/', '_', $name);
if ($fallback === null || $fallback === '') {
    $fallback = 'attachment';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file);
exit;
