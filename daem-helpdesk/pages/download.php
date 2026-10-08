<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$att = Database::one('SELECT * FROM attachments WHERE id = ?', [$id]);

if (!$att) {
    http_response_code(404);
    exit('Attachment not found.');
}

$ticket = Tickets::find((int) $att['ticket_id']);
if (!$ticket || !Tickets::canView($ticket)) {
    http_response_code(403);
    exit('Access denied.');
}

$file = APP_ROOT . '/storage/uploads/' . (int) $att['ticket_id'] . '/' . $att['stored_name'];
if (!is_file($file)) {
    http_response_code(404);
    exit('File missing from storage.');
}

header('Content-Type: ' . ($att['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($file));
header('Content-Disposition: attachment; filename="' . $att['original_name'] . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
