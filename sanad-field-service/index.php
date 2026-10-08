<?php
declare(strict_types=1);

/**
 * Sanad — Field Service & Job Management Platform
 * Front controller: maps ?p= routes to page files and enforces authorization.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$routes = [
    'login'            => ['pages/login.php', 'guest'],
    'logout'           => ['pages/logout.php', 'any'],
    'doc'              => ['pages/doc.php', 'any'],

    'dashboard'        => ['pages/dashboard.php', 'user'],
    'board'            => ['pages/board.php', 'office'],
    'jobs'             => ['pages/jobs.php', 'user'],
    'job'              => ['pages/job.php', 'user'],
    'job_new'          => ['pages/job_new.php', 'office'],
    'job_edit'         => ['pages/job_edit.php', 'office'],

    'customers'        => ['pages/customers.php', 'user'],
    'customer'         => ['pages/customer.php', 'user'],
    'customer_new'     => ['pages/customer_new.php', 'office'],
    'customer_edit'    => ['pages/customer_edit.php', 'office'],

    'contracts'        => ['pages/contracts.php', 'office'],
    'contract'         => ['pages/contract.php', 'office'],

    'parts'            => ['pages/parts.php', 'office'],

    'invoices'         => ['pages/invoices.php', 'finance'],
    'invoice'          => ['pages/invoice.php', 'finance'],

    'reports'          => ['pages/reports.php', 'finance'],
    'notifications'    => ['pages/notifications.php', 'user'],
    'profile'          => ['pages/profile.php', 'user'],

    'admin/users'      => ['pages/admin/users.php', 'admin'],
    'admin/services'   => ['pages/admin/services.php', 'admin'],
    'admin/settings'   => ['pages/admin/settings.php', 'admin'],
    'admin/audit'      => ['pages/admin/audit.php', 'admin'],

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
    case 'office':
        Auth::requireOffice();
        break;
    case 'finance':
        Auth::requireFinance();
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
