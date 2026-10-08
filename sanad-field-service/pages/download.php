<?php
declare(strict_types=1);

/**
 * Authenticated download of a file attached to a job.
 * Technicians may only download files of their own jobs.
 */

$id   = (int) ($_GET['id'] ?? 0);
$file = Database::one('SELECT * FROM job_files WHERE id = ?', [$id]);
if (!$file) {
    http_response_code(404);
    exit(t('e404.title'));
}

$job = Jobs::find((int) $file['job_id']);
if (!$job) {
    http_response_code(404);
    exit(t('e404.title'));
}
if (Auth::isTechnician() && (int) ($job['assigned_to'] ?? 0) !== Auth::id()) {
    http_response_code(403);
    exit(t('auth.denied'));
}

$path = APP_ROOT . '/storage/uploads/' . (int) $file['job_id'] . '/' . basename((string) $file['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit(t('e404.title'));
}

audit('job_file_download', 'job', (int) $file['job_id'], 'file=' . (string) $file['original_name']);

$mime = (string) ($file['mime'] ?: 'application/octet-stream');
$name = str_replace(['"', "\r", "\n"], '', (string) $file['original_name']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
