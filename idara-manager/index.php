<?php
declare(strict_types=1);

/**
 * Idara — Manager Workspace
 * Front controller: maps ?p= routes to page files and enforces authorization.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$routes = [
    'login'            => ['pages/login.php', 'guest'],
    'logout'           => ['pages/logout.php', 'any'],
    'dashboard'        => ['pages/dashboard.php', 'user'],
    'tasks'            => ['pages/tasks.php', 'user'],
    'task'             => ['pages/task_view.php', 'user'],
    'new-task'         => ['pages/task_new.php', 'user'],
    'approvals'        => ['pages/approvals.php', 'user'],
    'approval'         => ['pages/approval_view.php', 'user'],
    'new-approval'     => ['pages/approval_new.php', 'user'],
    'team'             => ['pages/team.php', 'user'],
    'correspondence'   => ['pages/correspondence.php', 'user'],
    'letter'           => ['pages/letter_view.php', 'user'],
    'new-letter'       => ['pages/letter_new.php', 'user'],
    'meetings'         => ['pages/meetings.php', 'user'],
    'meeting'          => ['pages/meeting_view.php', 'user'],
    'calendar'         => ['pages/calendar.php', 'user'],
    'delegations'      => ['pages/delegations.php', 'user'],
    'notifications'    => ['pages/notifications.php', 'user'],
    'profile'          => ['pages/profile.php', 'user'],
    'doc'              => ['pages/doc.php', 'any'],
    'admin/users'      => ['pages/admin/users.php', 'admin'],
    'admin/departments' => ['pages/admin/departments.php', 'admin'],
    'admin/categories' => ['pages/admin/categories.php', 'admin'],
    'admin/types'      => ['pages/admin/types.php', 'admin'],
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

// Accounts with a temporary (reset) password must change it before anything else.
if (Auth::current() && Auth::mustChangePassword() && !in_array($page, ['profile', 'api', 'logout', 'doc'], true)) {
    flash('warning', t('au.must_change'));
    redirect('profile');
}

require APP_ROOT . '/' . $file;
