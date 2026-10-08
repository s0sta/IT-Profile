<?php
declare(strict_types=1);

/**
 * Idara — JSON endpoints used by the frontend. Every action requires a signed-in user.
 *
 *   GET  ?p=api&a=notif_count              → {"unread":N}
 *   POST ?p=api&a=task_progress  id,progress→ {"ok":true,"progress":N}
 *   POST ?p=api&a=mark_all_read            → {"ok":true}
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!Auth::current()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

$action = (string) ($_GET['a'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/** Emit a JSON error body and stop. */
$fail = static function (int $code, string $error): void {
    http_response_code($code);
    echo json_encode(['error' => $error]);
    exit;
};

switch ($action) {
    case 'notif_count':
        echo json_encode(['unread' => Notifications::unreadCount(Auth::id())]);
        break;

    case 'task_progress':
        if (!$isPost) {
            $fail(405, 'method not allowed');
        }
        csrf_check();
        $me       = Auth::current();
        $id       = (int) ($_POST['id'] ?? 0);
        $progress = max(0, min(100, (int) ($_POST['progress'] ?? 0)));
        $task     = Tasks::find($id);
        if (!$task) {
            $fail(404, 'not found');
        }
        if (!$me || !Tasks::canEdit($task, $me)) {
            $fail(403, 'forbidden');
        }
        Tasks::setProgress($id, $progress);
        echo json_encode(['ok' => true, 'progress' => $progress]);
        break;

    case 'mark_all_read':
        if (!$isPost) {
            $fail(405, 'method not allowed');
        }
        csrf_check();
        Notifications::markAllRead(Auth::id());
        echo json_encode(['ok' => true]);
        break;

    default:
        $fail(404, 'unknown action');
}
exit;
