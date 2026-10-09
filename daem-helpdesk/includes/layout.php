<?php
declare(strict_types=1);

/**
 * Shared page layout: sidebar navigation, topbar (theme toggle, notifications,
 * user menu), flash messages and footer. Pages call layout_header() /
 * layout_footer() around their content.
 */

function icon(string $name): string
{
    static $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'ticket'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V7z"/><path d="M12 7v10" stroke-dasharray="2 3"/></svg>',
        'book'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V4H6.5A2.5 2.5 0 0 0 4 6.5v13z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/></svg>',
        'users'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5c3 .3 5.5 2.5 5.5 5.5"/></svg>',
        'tags'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11V4a1 1 0 0 1 1-1h7l10 10-7 7L3 11z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
        'bookopen'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6c-2-1.5-5-2-8-1.5V19c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5V4.5C17 4 14 4.5 12 6z"/><path d="M12 6v14.5"/></svg>',
        'sliders'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h13M20 18h1"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        'list'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/></svg>',
        'chart'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
        'plus'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>',
        'bell'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>',
        'search'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>',
        'logout'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
        'user'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>',
        'moon'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>',
        'sun'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
        'menu'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
        'help'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.4 2.3c-.6.3-.9.8-.9 1.4v.6"/><circle cx="12" cy="17" r="1"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function status_badge(string $status): string
{
    return '<span class="badge badge-status badge-' . e($status) . '">' . e(statuses()[$status] ?? $status) . '</span>';
}

function priority_badge(string $priority): string
{
    return '<span class="badge badge-priority badge-p-' . e($priority) . '">' . e(priorities()[$priority] ?? $priority) . '</span>';
}

function role_badge(string $role): string
{
    return '<span class="badge badge-role badge-role-' . e($role) . '">' . e(roles()[$role] ?? $role) . '</span>';
}

/** Shared ticket table (used by dashboard, tickets list and reports). */
function ticket_table(array $rows, bool $showRequester = true): void
{
    if (!$rows) {
        echo '<div class="empty">' . e(t('tickets.empty')) . '</div>';
        return;
    }
    ?>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e(t('tickets.col_ticket')) ?></th>
          <th><?= e(t('tickets.col_subject')) ?></th>
          <th><?= e(t('common.category')) ?></th>
          <th><?= e(t('common.priority')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <?php if ($showRequester): ?><th><?= e(t('common.requester')) ?></th><?php endif; ?>
          <th><?= e(t('common.assignee')) ?></th>
          <th><?= e(t('common.updated')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $t):
            $sla = sla_state($t);
            $overdue = $sla === 'breached';
        ?>
        <tr>
          <td>
            <a class="ref-link" href="<?= u('ticket&id=' . (int) $t['id']) ?>"><?= e($t['ref']) ?></a>
            <?php if ($overdue): ?><span class="sla-flag" title="<?= e(t('tickets.sla_breach')) ?>">⏰</span><?php endif; ?>
          </td>
          <td class="cell-subject"><?= e($t['subject']) ?></td>
          <td><?= e($t['category_name'] ?? '—') ?></td>
          <td><?= priority_badge($t['priority']) ?></td>
          <td><?= status_badge($t['status']) ?></td>
          <?php if ($showRequester): ?><td><?= e($t['requester_name'] ?? '—') ?></td><?php endif; ?>
          <td><?= e($t['assignee_name'] ?? '—') ?></td>
          <td class="cell-muted"><?= e(time_ago($t['updated_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php
}

function layout_header(string $title, string $active = ''): void
{
    $user   = Auth::current();
    $unread = Notifications::unreadCount(Auth::id());
    $site   = setting('site_name', 'Daem');

    $nav = [
        ['dashboard', t('nav.dashboard'), 'dashboard'],
        ['tickets', t('nav.tickets'), 'ticket'],
        ['kb', t('nav.knowledge_base'), 'book'],
        ['doc', t('nav.doc'), 'help'],
    ];
    $adminNav = [];
    if (Auth::isAdmin()) {
        $adminNav = [
            ['admin/users', t('nav.users'), 'users'],
            ['admin/categories', t('nav.categories'), 'tags'],
            ['admin/kb', t('nav.kb_admin'), 'bookopen'],
            ['admin/settings', t('nav.settings'), 'sliders'],
            ['admin/audit', t('nav.audit'), 'list'],
            ['admin/reports', t('nav.reports'), 'chart'],
        ];
    }
    ?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=2">
<script>
(function(){try{var t=localStorage.getItem('daem-theme');
if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}
document.documentElement.setAttribute('data-theme',t);}catch(err){}})();
</script>
</head>
<body>
<div class="app">
  <div class="sidebar-overlay" id="sidebar-overlay"></div>
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= u('dashboard') ?>">
      <span class="brand-logo">◈</span>
      <span class="brand-text"><?= e($site) ?><span class="brand-sub"><?= e(t('brand.tagline')) ?></span></span>
    </a>
    <nav class="nav">
      <p class="nav-label"><?= e(t('nav.workspace')) ?></p>
      <?php foreach ($nav as [$key, $label, $ic]): ?>
        <a class="nav-item <?= $active === $key ? 'active' : '' ?>" href="<?= u($key) ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
      <?php if ($adminNav): ?>
        <p class="nav-label"><?= e(t('nav.administration')) ?></p>
        <?php foreach ($adminNav as [$key, $label, $ic]): ?>
          <a class="nav-item <?= $active === $key ? 'active' : '' ?>" href="<?= u($key) ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <a class="btn btn-primary sidebar-new" href="<?= u('new') ?>"><?= icon('plus') ?> <?= e(t('nav.new_ticket')) ?></a>
    <div class="sidebar-foot">v1.3 · by s0sta</div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn" id="nav-toggle" aria-label="Menu"><?= icon('menu') ?></button>
      <h1 class="page-title"><?= e($title) ?></h1>
      <div class="topbar-actions">
        <?= daem_lang_switcher() ?>
        <button class="icon-btn" id="theme-toggle" aria-label="<?= e(t('nav.theme')) ?>"><?= icon('moon') ?></button>
        <a class="icon-btn bell" href="<?= u('notifications') ?>" aria-label="<?= e(t('nav.notifications')) ?>"><?= icon('bell') ?><?php if ($unread): ?><span class="bell-badge"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?></a>
        <div class="user-menu" id="user-menu">
          <button class="user-chip" id="user-chip" aria-haspopup="true">
            <span class="avatar"><?= e(initials($user['name'])) ?></span>
            <span class="user-meta"><span class="user-name"><?= e($user['name']) ?></span><span class="user-role"><?= e(roles()[$user['role']] ?? $user['role']) ?></span></span>
            <span class="chev">▾</span>
          </button>
          <div class="dropdown" id="user-dropdown" hidden>
            <a href="<?= u('profile') ?>"><?= icon('user') ?> <?= e(t('nav.profile')) ?></a>
            <a href="<?= u('logout') ?>" class="danger"><?= icon('logout') ?> <?= e(t('nav.sign_out')) ?></a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <?php foreach (take_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
<?php
}

function layout_footer(): void
{
    ?>
    </main>
    <footer class="footer">© <?= e(t('nav.footer', ['year' => date('Y'), 'site' => setting('site_name', 'Daem')])) ?></footer>
  </div>
</div>
<script src="assets/js/app.js?v=2"></script>
</body>
</html>
<?php
}

/**
 * Full styled error page (404 / 403 / 500) — used by the router and by detail
 * pages that cannot show their record. Renders its own page shell and exits.
 */
function render_error_page(int $code, string $title, string $message): void
{
    http_response_code($code);
    $site = setting('site_name', 'Daem');
    $back = Auth::current() ? u('dashboard') : u('login');
    $label = Auth::current() ? t('e404.back') : t('auth.sign_in');
    ?>
<!doctype html>
<html lang="<?= e(daem_current_lang()) ?>" dir="<?= e(daem_lang_dir()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css?v=2">
<script>
(function(){try{var t=localStorage.getItem('daem-theme');
if(!t){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}
document.documentElement.setAttribute('data-theme',t);}catch(err){}})();
</script>
</head>
<body class="standalone">
  <div class="login-lang"><?= daem_lang_switcher() ?></div>
  <div class="error-card">
    <h1><?= e((string) $code) ?></h1>
    <p class="error-title"><?= e($title) ?></p>
    <p><?= e($message) ?></p>
    <a class="btn btn-primary" href="<?= e($back) ?>"><?= e($label) ?></a>
  </div>
</body>
</html>
<?php
    exit;
}
