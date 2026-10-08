<?php
declare(strict_types=1);

/**
 * JSON endpoints used by the frontend (all require a signed-in user).
 *   ?p=api&a=kb_search&q=...        → KB suggestions while typing a ticket subject
 *   ?p=api&a=notif_count             → unread notification count for the bell
 *   ?p=api&a=mark_all_read (POST)    → mark every notification as read
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!Auth::current()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

$action = (string) ($_GET['a'] ?? '');

switch ($action) {
    case 'kb_search':
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 3) {
            echo json_encode(['items' => []]);
            break;
        }
        $rows = Kb::articles(null, $q, 5, 0, true);
        $items = [];
        foreach ($rows as $r) {
            $items[] = ['title' => $r['title'], 'slug' => $r['slug'], 'excerpt' => excerpt(strip_tags($r['body']), 90)];
        }
        echo json_encode(['items' => $items]);
        break;

    case 'notif_count':
        echo json_encode(['unread' => Notifications::unreadCount(Auth::id())]);
        break;

    case 'mark_all_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'method not allowed']);
            break;
        }
        csrf_check();
        Notifications::markAllRead(Auth::id());
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'unknown action']);
}
exit;
