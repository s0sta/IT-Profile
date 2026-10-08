<?php
declare(strict_types=1);

/**
 * Small JSON API for the UI (notification counter, quick job search).
 * Public actions are limited to notification counting.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$action = (string) ($_GET['a'] ?? '');

if ($action === 'notif_count') {
    if (!Auth::current()) {
        echo json_encode(['unread' => 0]);
        exit;
    }
    echo json_encode(['unread' => Notifications::unreadCount(Auth::id())]);
    exit;
}

if (!Auth::current()) {
    http_response_code(401);
    echo json_encode(['error' => 'auth_required']);
    exit;
}

if ($action === 'job_search') {
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) {
        echo json_encode(['items' => []]);
        exit;
    }
    $rows = Jobs::list(['q' => $q], 8, 0);
    $items = [];
    foreach ($rows as $job) {
        $items[] = [
            'number'   => (string) $job['number'],
            'title'    => (string) $job['title'],
            'customer' => (string) ($job['customer_name'] ?? ''),
            'status'   => (string) (job_statuses()[$job['status']] ?? $job['status']),
            'url'      => u('job&id=' . (int) $job['id']),
        ];
    }
    echo json_encode(['items' => $items]);
    exit;
}

if ($action === 'mark_all_read') {
    Notifications::markAllRead(Auth::id());
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown_action']);
