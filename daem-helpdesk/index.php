<?php
declare(strict_types=1);

/**
 * Daem — IT Help Desk Platform
 * Front controller: maps ?p= routes to page files and enforces authorization.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$routes = [
    'login'            => ['pages/login.php', 'guest'],
    'logout'           => ['pages/logout.php', 'any'],
    'dashboard'        => ['pages/dashboard.php', 'user'],
    'tickets'          => ['pages/tickets.php', 'user'],
    'new'              => ['pages/ticket_new.php', 'user'],
    'ticket'           => ['pages/ticket_view.php', 'user'],
    'kb'               => ['pages/kb.php', 'user'],
    'article'          => ['pages/article.php', 'user'],
    'notifications'    => ['pages/notifications.php', 'user'],
    'profile'          => ['pages/profile.php', 'user'],
    'doc'              => ['pages/doc.php', 'any'],
    'admin/users'      => ['pages/admin/users.php', 'admin'],
    'admin/categories' => ['pages/admin/categories.php', 'admin'],
    'admin/kb'         => ['pages/admin/kb.php', 'admin'],
    'admin/settings'   => ['pages/admin/settings.php', 'admin'],
    'admin/audit'      => ['pages/admin/audit.php', 'admin'],
    'admin/reports'    => ['pages/admin/reports.php', 'admin'],
    'api'              => ['pages/api.php', 'any'],
    'download'         => ['pages/download.php', 'user'],
];

$page = $_GET['p'] ?? 'dashboard';
if ($page === '' || $page === '/') {
    $page = 'dashboard';
}

if (!isset($routes[$page])) {
    http_response_code(404);
    require __DIR__ . '/pages/404.php';
    exit;
}

[$file, $minRole] = $routes[$page];

switch ($minRole) {
    case 'admin':
        Auth::requireAdmin();
        break;
    case 'user':
        Auth::requireLogin();
        break;
    case 'guest':
        if (Auth::current()) {
            redirect('dashboard');
        }
        break;
    case 'any':
    default:
        break;
}

require APP_ROOT . '/' . $file;
